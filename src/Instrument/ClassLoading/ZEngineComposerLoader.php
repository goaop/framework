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

namespace Go\Instrument\ClassLoading;

use Closure;
use Composer\Autoload\ClassLoader;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
use Go\Instrument\ZEngine\ZEngineClassWeaver;

/**
 * Composer loader wrapper of the z-engine driver: loads the class natively, then weaves it
 *
 * The original file is included exactly as composer would (opcache caches it, magic constants
 * and line numbers are untouched). The weaving happens INSIDE loadClass(), right after the
 * include: a subclass whose linking triggered this autoload therefore links against the already
 * woven parent, which is the order the engine mutation supports.
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 *
 * @internal Framework service, not a public extension point
 */
final class ZEngineComposerLoader implements ComposerLoaderDecorator
{
    /**
     * Includes a file in an isolated scope: neither $this nor the loader state leak into it
     *
     * @var (Closure(string): void)|null
     */
    private static ?Closure $includeFile = null;

    /**
     * File enumerator applying the includePaths/excludePaths of the kernel
     */
    private readonly Enumerator $fileEnumerator;

    /**
     * Lazy-initialized filter for allowed files
     */
    private ?Closure $isAllowedFilter = null;

    /**
     * @phpstan-param KernelOptions $options Kernel options
     */
    public function __construct(
        private readonly ClassLoader $original,
        private readonly AspectContainer $container,
        array $options,
    ) {
        // The framework itself and its runtime dependencies (z-engine included) are already part of excludePaths
        $this->fileEnumerator = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
    }

    /**
     * Wraps every composer class loader registered with SPL (unwrapping an earlier wrapper of either driver)
     *
     * @phpstan-param KernelOptions $options Aspect kernel options
     *
     * @return bool Whether a composer loader was found and wrapped
     */
    public static function init(array $options, AspectContainer $container): bool
    {
        $wasInitialized = false;
        $loaders        = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader)) {
                $originalLoader = $loader[0];
                if ($originalLoader instanceof ComposerLoaderDecorator) {
                    $originalLoader = $originalLoader->getOriginalLoader();
                }
                if ($originalLoader instanceof ClassLoader) {
                    $loader[0]      = new self($originalLoader, $container, $options);
                    $wasInitialized = true;
                }
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return $wasInitialized;
    }

    public function getOriginalLoader(): ClassLoader
    {
        return $this->original;
    }

    /**
     * Loads a class natively and weaves it when its file is within the weaving scope
     *
     * @return true|null True if loaded, null otherwise (the same contract as composer's loader)
     */
    public function loadClass(string $class): ?true
    {
        $file = $this->original->findFile($class);
        if ($file === false) {
            return null;
        }

        (self::$includeFile ??= static function (string $file): void {
            include $file;
        })($file);

        $resolved = PathResolver::realpath($file);
        if (is_string($resolved)) {
            $file = $resolved;
        }
        if (($this->isAllowedFilter ??= $this->fileEnumerator->getPathFilter())($file)) {
            $this->container->getService(ZEngineClassWeaver::class)->weaveLoadedClass($class, $file);
        }

        return true;
    }

    /**
     * Finds the original file of a class (composer's answer, never a filter stream)
     *
     * @return string|false
     */
    public function findFile(string $class): string|false
    {
        return $this->original->findFile($class);
    }
}
