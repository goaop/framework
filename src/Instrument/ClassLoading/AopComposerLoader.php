<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Closure;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\FileSystem\Enumerator;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Composer\Autoload\ClassLoader;

/**
 * AopComposerLoader class is responsible to use a weaver for classes instead of original one
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
final class AopComposerLoader implements ComposerLoaderDecorator
{
    /**
     * File enumerator
     */
    protected Enumerator $fileEnumerator;

    /**
     * Runtime class map: woven class name => cached file (also fed to composer's classmap)
     *
     * @var array<class-string, string>
     */
    private array $classMap;

    /**
     * Classes known to the cache but not transformed - served natively by composer
     *
     * @var array<class-string, true>
     */
    private array $skippedClasses;

    /**
     * Cache index, consulted in debug mode for files the cache knows as untransformed
     */
    private CachePathManager $cachePathManager;

    /**
     * Includes a file in an isolated scope: neither $this nor the loader state leak into it
     *
     * @var (Closure(string): void)|null
     */
    private static ?Closure $includeFile = null;

    /**
     * Lazy-initialized filter for allowed files
     */
    private ?Closure $isAllowedFilter = null;

    /**
     * Whether the kernel is in production (non-debug) mode
     */
    private bool $isProduction = false;

    /**
     * Constructs an wrapper for the composer loader
     *
     * @phpstan-param KernelOptions $options Configuration options
     */
    public function __construct(
        protected readonly ClassLoader $original,
        private readonly AspectContainer $container,
        protected readonly array $options,
    ) {
        // The framework itself and its runtime dependencies are already part of excludePaths (see AspectKernel)
        $fileEnumerator       = new Enumerator($options['appDir'], $options['includePaths'], $options['excludePaths']);
        $this->fileEnumerator = $fileEnumerator;

        $cachePathManager       = $container->getService(CachePathManager::class);
        $this->cachePathManager = $cachePathManager;
        $this->classMap         = $cachePathManager->queryClassMap();
        $this->skippedClasses   = $cachePathManager->querySkippedClasses();

        // In production the woven class map is handed to composer directly: its findFile()
        // consults the class map before PSR-4/PSR-0, so woven classes resolve natively to
        // their cached files. Untransformed classes are deliberately NOT added - composer
        // already resolves them to their original files.
        if (!$options['debug'] && $this->classMap !== []) {
            $original->addClassMap($this->classMap);
        }
    }

    /**
     * Initialize aspect autoloader and returns status whether initialization was successful or not
     *
     * Replaces original composer autoloader with wrapper. A loader wrapped by an earlier call
     * (of either weaving driver) is wrapped again around its original composer loader, with the
     * given options and container.
     *
     * @phpstan-param KernelOptions $options Aspect kernel options
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
                    $loader[0]      = new AopComposerLoader($originalLoader, $container, $options);
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

    /**
     * Returns the wrapped composer class loader
     *
     * The wrapper replaces composer's loader in spl_autoload_functions(), so tools that look for
     * a ClassLoader instance there should unwrap it with this method
     * (ClassLoader::getRegisteredLoaders() is not affected).
     */
    public function getOriginalLoader(): ClassLoader
    {
        return $this->original;
    }

    /**
     * Finds the original source file of a class through the composer loaders wrapped by the kernel (by this
     * loader or by the loader of the z-engine driver), without loading the class
     *
     * @return string|null Path given by composer, or null when no wrapped composer loader knows the class
     */
    public static function findOriginalFile(string $class): ?string
    {
        foreach (spl_autoload_functions() as $loader) {
            if (is_array($loader) && $loader[0] instanceof ComposerLoaderDecorator) {
                $file = $loader[0]->getOriginalLoader()->findFile($class);
                if ($file !== false) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Autoload a class by it's name
     *
     * @return true|null True if loaded, null otherwise (the same contract as composer's loader)
     */
    public function loadClass(string $class): ?true
    {
        $file = $this->findFile($class);

        if ($file !== false) {
            (self::$includeFile ??= static function (string $file): void {
                include $file;
            })($file);

            return true;
        }

        return null;
    }

    /**
     * Finds either the path to the file where the class is defined,
     * or gets the appropriate php://filter stream for the given class.
     *
     * @return string|false The path/resource if found, false otherwise.
     */
    public function findFile(string $class): false|string
    {
        if ($this->isAllowedFilter === null) {
            $this->isAllowedFilter = $this->fileEnumerator->getPathFilter();
            $this->isProduction    = !$this->options['debug'];
        }

        $file = $this->original->findFile($class);

        if ($file !== false) {
            if ($this->isProduction && (isset($this->classMap[$class]) || isset($this->skippedClasses[$class]))) {
                // Known class: composer already resolved it to the cached file (via the
                // injected class map) or to the untouched original - nothing left to do
                return $file;
            }
            $resolved = PathResolver::realpath($file);
            if (is_string($resolved)) {
                $file = $resolved;
            }
            // Debug mode: a woven class (known from the class map) goes to the filter without a freshness check of its
            // own, the filter checks the record anyway
            $mayBeUntransformed = !$this->isProduction && !isset($this->classMap[$class]);
            if (($this->isAllowedFilter)($file) && (!$mayBeUntransformed || !$this->isFreshUntransformedFile($file))) {
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }

    /**
     * Debug mode: checks whether the cache knows the file as untransformed, by a record that is still fresh
     *
     * Such a file is included by its original path: opcache caches it, while it never caches a php://filter include,
     * which compiles the file again on every request. Woven files keep the filter, so that their magic constants and
     * breakpoints point at the original file, and so do stale records and misses, to be woven again.
     */
    private function isFreshUntransformedFile(string $file): bool
    {
        $cacheState = $this->cachePathManager->queryFreshCacheState($file, $this->container);

        return $cacheState !== null && !isset($cacheState['cacheUri']);
    }
}
