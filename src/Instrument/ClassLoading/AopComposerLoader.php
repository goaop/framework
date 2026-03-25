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
     * Instance of original autoloader
     */
    protected ClassLoader $original;

    /**
     * AOP kernel options
     *
     * @phpstan-var KernelOptions
     */
    protected array $options;

    /**
     * File enumerator
     */
    protected Enumerator $fileEnumerator;

    /**
     * Cache state
     *
     * @var array<string, mixed>
     */
    private array $cacheState;

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
    public function __construct(ClassLoader $original, AspectContainer $container, array $options)
    {
        $this->options  = $options;
        $this->original = $original;

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
        $this->cacheState     = $container->getService(CachePathManager::class)->queryCacheState() ?? [];
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
                $originalLoader = $loader[0] ?? null;
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
            $resolved = PathResolver::realpath($file);
            if (is_string($resolved)) {
                $file = $resolved;
            }
            $cacheState = $this->cacheState[$file] ?? null;
            if ($cacheState && $this->isProduction) {
                $cacheUri = is_array($cacheState) && is_string($cacheState['cacheUri'] ?? null) ? $cacheState['cacheUri'] : null;
                $file     = $cacheUri ?: $file;
            } elseif (($this->isAllowedFilter)(new SplFileInfo($file))) {
                // can be optimized here with $cacheState even for debug mode, but no needed right now
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
