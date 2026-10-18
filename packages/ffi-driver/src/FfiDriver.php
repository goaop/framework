<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Driver\Ffi;

use Closure;
use Composer\InstalledVersions;
use Go\Aop\Features;
use Go\Core\AdviceMatcher;
use Go\Core\AdvisorIndex;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Driver\Ffi\Exception\EngineConfigurationException;
use Go\Driver\WeavingDriver;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use Throwable;
use ZEngine\Core;

/**
 * The ffi weaving driver: runtime method-table weaving through lisachenko/z-engine (PHP FFI)
 *
 * Classes load natively. Right after a class is loaded, the body of every advised method is swapped
 * in place with a generated dispatcher while the previous body stays callable under the private
 * `<method>OriginalAlias`, and an inherited advised method gets an engine-published override. No
 * source rewriting, no stream filter or wrapper, no include interception: `'driver' => FfiDriver::class`.
 *
 * Boot order matters: the JIT guard runs BEFORE z-engine is booted, so z-engine's own classes are
 * compiled with the JIT already off when the engine was not auto-booted from composer's autoload
 * files (`ZENGINE_AUTOBOOT=0`).
 *
 * @phpstan-import-type JitReport from JitGuard
 * @phpstan-type BootReport array{driver: class-string, php: string, opcache: bool, jit: JitReport}
 */
final class FfiDriver implements WeavingDriver
{
    /**
     * Container key of the boot report (see getBootReport())
     */
    public const string BOOT_REPORT = 'kernel.ffi.boot';

    /**
     * Composer name of the engine package, whose code is never woven
     */
    private const string ENGINE_PACKAGE = 'lisachenko/z-engine';

    /**
     * @var Closure(): bool
     */
    private readonly Closure $isEngineInstalled;

    /**
     * @var Closure(): void
     */
    private readonly Closure $bootEngine;

    /**
     * The kernel creates the driver without arguments; the closures are seams for the tests
     *
     * @param (Closure(): bool)|null $isEngineInstalled Whether z-engine is installed, class_exists(Core::class) by default
     * @param (Closure(): void)|null $bootEngine        Boots z-engine unless it is initialized, Core::init() by default
     */
    public function __construct(?Closure $isEngineInstalled = null, ?Closure $bootEngine = null)
    {
        $this->isEngineInstalled = $isEngineInstalled ?? static fn(): bool => class_exists(Core::class);
        $this->bootEngine        = $bootEngine ?? static function (): void {
            if (!Core::isInitialized()) {
                Core::init();
            }
        };
    }

    /**
     * @throws EngineConfigurationException When z-engine is missing or cannot boot, the JIT stays on, or the
     *                                      kernel options enable include interception
     */
    public function boot(AspectKernel $kernel, AspectContainer $container): void
    {
        $options = $kernel->getOptions();
        // Includes are rewritten by a source transformer: there is no source transformation here
        if (($options['features'] & Features::INTERCEPT_INCLUDES) !== 0) {
            throw EngineConfigurationException::includeInterceptionNotSupported();
        }
        if (!($this->isEngineInstalled)()) {
            throw EngineConfigurationException::engineNotInstalled();
        }

        $jitReport = JitGuard::enforce();

        try {
            ($this->bootEngine)();
        } catch (Throwable $failure) {
            throw EngineConfigurationException::engineBootFailed($failure);
        }

        $container->add(self::BOOT_REPORT, [
            'driver'  => self::class,
            'php'     => PHP_VERSION,
            'opcache' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
            'jit'     => $jitReport,
        ]);

        // The engine must never weave the code it runs on: the installed engine joins the excluded paths
        $enginePath = InstalledVersions::isInstalled(self::ENGINE_PACKAGE) ? InstalledVersions::getInstallPath(self::ENGINE_PACKAGE) : null;
        $enginePath = $enginePath !== null ? PathResolver::realpath($enginePath) : null;
        if (is_string($enginePath)) {
            $options['excludePaths'][] = $enginePath;
        }

        $container->addLazyService(DonorCacheIndex::class, fn(AspectContainer $container): DonorCacheIndex => new DonorCacheIndex(
            $container->getService(CachePathManager::class),
            $options,
        ));
        $container->addLazyService(MethodTableRewriter::class, fn(): MethodTableRewriter => new MethodTableRewriter());
        $container->addLazyService(ClassWeaver::class, fn(AspectContainer $container): ClassWeaver => new ClassWeaver(
            $container,
            $container->getService(AdviceMatcher::class),
            $container->getService(AdvisorIndex::class),
            $container->getService(CachePathManager::class),
            $container->getService(DonorCacheIndex::class),
            $container->getService(MethodTableRewriter::class),
        ));

        FfiComposerLoader::init($options, $container);
    }

    /**
     * What the boot did: PHP version, opcache state and the JIT guard report
     *
     * @return BootReport
     */
    public static function getBootReport(AspectContainer $container): array
    {
        /** @var BootReport $report Shape of our own container value */
        $report = $container->get(self::BOOT_REPORT);

        return $report;
    }
}
