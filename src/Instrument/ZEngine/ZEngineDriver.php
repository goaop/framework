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

namespace Go\Instrument\ZEngine;

use Closure;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\ZEngineComposerLoader;
use Throwable;
use ZEngine\Core;

/**
 * Boot sequence of the z-engine weaving driver, run by AspectKernel::init() in place of the
 * transformer chain and the stream-filter autoloader
 *
 * Order matters: the JIT guard runs BEFORE z-engine is booted, so z-engine's own classes are
 * compiled with the JIT already off when the engine was not auto-booted from composer's
 * autoload files (`ZENGINE_AUTOBOOT=0`).
 *
 * @phpstan-import-type JitReport from JitGuard
 * @phpstan-type BootReport array{driver: string, php: string, opcache: bool, jit: JitReport}
 *
 * @internal Framework service, not a public extension point
 */
final class ZEngineDriver
{
    /**
     * Container key of the boot report (see getBootReport())
     */
    public const string BOOT_REPORT = 'kernel.zengine.boot';

    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * @param (Closure(): bool)|null $isEngineInstalled Whether z-engine is installed, class_exists(Core::class) by default (tests)
     * @param (Closure(): void)|null $bootEngine        Boots z-engine unless it is initialized, Core::init() by default (tests)
     *
     * @throws InvalidConfigurationException When z-engine is missing, cannot boot, or the JIT is on where it may not be switched off
     */
    public static function boot(
        AspectKernel $kernel,
        AspectContainer $container,
        ?Closure $isEngineInstalled = null,
        ?Closure $bootEngine = null,
    ): void {
        $isEngineInstalled ??= static fn(): bool => class_exists(Core::class);
        $bootEngine        ??= static function (): void {
            if (!Core::isInitialized()) {
                Core::init();
            }
        };

        if (!$isEngineInstalled()) {
            throw new InvalidConfigurationException(
                'The zengine weaving driver requires lisachenko/z-engine: install the z-engine branch '
                . 'matching the PHP minor version (composer require lisachenko/z-engine:8.5.x-dev on PHP 8.5) '
                . 'with ext-ffi enabled.',
            );
        }

        $jitReport = JitGuard::enforce();

        try {
            $bootEngine();
        } catch (Throwable $failure) {
            throw new InvalidConfigurationException(
                'The zengine weaving driver cannot boot z-engine: ' . $failure->getMessage(),
                0,
                $failure,
            );
        }

        $container->add(self::BOOT_REPORT, [
            'driver'  => 'zengine',
            'php'     => PHP_VERSION,
            'opcache' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
            'jit'     => $jitReport,
        ]);

        $container->addLazyService(DonorCacheIndex::class, fn(AspectContainer $container): DonorCacheIndex => new DonorCacheIndex(
            $container->getService(CachePathManager::class),
            $kernel->getOptions(),
        ));
        $container->addLazyService(MethodTableRewriter::class, fn(): MethodTableRewriter => new MethodTableRewriter());
        $container->addLazyService(ZEngineClassWeaver::class, fn(AspectContainer $container): ZEngineClassWeaver => new ZEngineClassWeaver(
            $container,
            $container->getService(AdviceMatcher::class),
            $container->getService(CachedAspectLoader::class),
            $container->getService(CachePathManager::class),
            $container->getService(DonorCacheIndex::class),
            $container->getService(MethodTableRewriter::class),
        ));

        ZEngineComposerLoader::init($kernel->getOptions(), $container);
    }

    /**
     * What the boot did: PHP version, opcache state and the JIT guard report
     *
     * @return BootReport
     */
    public static function getBootReport(AspectContainer $container): array
    {
        /** @var BootReport $report Shape of our own container value */
        $report = $container->getValue(self::BOOT_REPORT);

        return $report;
    }
}
