<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\ClassLoading;

use Go\Core\AspectKernel;
use Go\Instrument\PathResolver;
use RuntimeException;

/**
 * Runtime file path resolver for AOP-transformed code.
 *
 * Contains static methods that are injected into woven source code by source transformers:
 * - rewrite() — injected by FilterInjectorTransformer into include/require expressions
 * - resolveFileName() — injected by MagicConstantTransformer into getFileName() calls
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class AopFileResolver
{
    /**
     * Php filter definition
     */
    public const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Name of the filter to inject
     */
    protected static ?string $filterName = null;

    /**
     * Kernel options
     *
     * @phpstan-var KernelOptions
     */
    protected static array $options;

    /**
     * Root path of application
     */
    protected static string $rootPath = '';

    /**
     * Path to rewrite to (cache directory)
     */
    protected static string $rewriteToPath = '';

    protected static ?CachePathManager $cachePathManager = null;

    /**
     * Configures the resolver with kernel options and runtime dependencies.
     *
     * Must be called once during kernel initialization, before any transformed code is loaded.
     */
    public static function configure(AspectKernel $kernel, string $filterName, CachePathManager $cacheManager): void
    {
        if (self::$filterName !== null) {
            throw new RuntimeException('AopFileResolver can be configured only once.');
        }
        self::$options          = $kernel->getOptions();
        self::$filterName       = $filterName;
        self::$cachePathManager = $cacheManager;
        self::$rootPath         = self::$options['appDir'];
        self::$rewriteToPath    = self::$options['cacheDir'] ?? '';
    }

    /**
     * Replace source path with correct one
     *
     * This operation can check for cache, can rewrite paths, add additional filters and much more
     *
     * @param string $originalResource Initial resource to include
     * @param string $originalDir Path to the directory from where include was called for resolving relative resources
     */
    public static function rewrite(string $originalResource, string $originalDir = ''): string
    {
        static $appDir, $cacheDir, $debug;
        if ($appDir === null) {
            extract(self::$options, EXTR_IF_EXISTS);
        }

        $resource = $originalResource;
        if ($resource[0] !== '/') {
            $shouldCheckExistence = true;
            $resource
                =  PathResolver::realpath($resource, $shouldCheckExistence)
                ?: PathResolver::realpath("{$originalDir}/{$resource}", $shouldCheckExistence)
                ?: $originalResource;
        }
        $cachedResource = self::$cachePathManager !== null
            ? self::$cachePathManager->getCachePathForResource($resource)
            : false;

        // If the cache is disabled, resource path not resolvable, or no cache yet, then use on-fly method
        if ($cachedResource === false || !$cacheDir || $debug || !file_exists($cachedResource)) {
            return self::PHP_FILTER_READ . self::$filterName . '/resource=' . $resource;
        }

        return $cachedResource;
    }

    /**
     * Resolves file name from the cache directory to the real application root dir
     */
    public static function resolveFileName(string $fileName): string
    {
        $suffix = '.php';
        $pathParts = explode($suffix, str_replace(
            [self::$rewriteToPath, DIRECTORY_SEPARATOR . '_proxies'],
            [self::$rootPath, ''],
            $fileName
        ));
        // throw away namespaced path from actual filename
        return $pathParts[0] . $suffix;
    }
}
