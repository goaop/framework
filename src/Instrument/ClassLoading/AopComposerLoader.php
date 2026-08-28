<?php

declare(strict_types = 1);
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
use SplFileInfo;
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
class AopComposerLoader
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
     * Was initialization successful or not
     */
    private static bool $wasInitialized = false;

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
        AspectContainer $container,
        protected readonly array $options
    ) {
        $prefixes     = $original->getPrefixes();
        $excludePaths = $options['excludePaths'];

        if (!empty($prefixes)) {
            // Let's exclude core dependencies from that list
            if (isset($prefixes['Dissect'])) {
                $excludePaths[] = $prefixes['Dissect'][0];
            }
        }

        $fileEnumerator       = new Enumerator($options['appDir'], $options['includePaths'], $excludePaths);
        $this->fileEnumerator = $fileEnumerator;

        $cachePathManager     = $container->getService(CachePathManager::class);
        $this->classMap       = $cachePathManager->queryClassMap();
        $this->skippedClasses = $cachePathManager->querySkippedClasses();

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
     * Replaces original composer autoloader with wrapper
     *
     * @phpstan-param KernelOptions $options Aspect kernel options
     */
    public static function init(array $options, AspectContainer $container): bool
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as &$loader) {
            $loaderToUnregister = $loader;
            if (is_array($loader)) {
                $originalLoader = $loader[0];
                if ($originalLoader instanceof ClassLoader) {
                    $loader[0] = new AopComposerLoader($originalLoader, $container, $options);
                    self::$wasInitialized = true;
                }
            }
            spl_autoload_unregister($loaderToUnregister);
        }
        unset($loader);

        foreach ($loaders as $loader) {
            spl_autoload_register($loader);
        }

        return self::$wasInitialized;
    }

    /**
     * Autoload a class by it's name
     */
    public function loadClass(string $class): void
    {
        $file = $this->findFile($class);

        if ($file !== false) {
            include $file;
        }
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
            $this->isAllowedFilter = $this->fileEnumerator->getFilter();
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
            if (($this->isAllowedFilter)(new SplFileInfo($file))) {
                // can be optimized here with the class map even for debug mode, but no needed right now
                $file = FilterInjectorTransformer::rewrite($file);
            }
        }

        return $file;
    }

    /**
     * Whether or not loader was initialized
     */
    public static function wasInitialized(): bool
    {
        return self::$wasInitialized;
    }
}
