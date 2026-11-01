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

namespace Go\Instrument\Transformer\Weaving;

use Closure;
use FilesystemIterator;
use Go\Core\AspectContainer;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Writes the generated class and function proxies into the cache directory
 *
 * Every save returns the statement the woven file includes the proxy with, relative to AOP_CACHE_DIR. Writes are
 * atomic: a concurrent request including a proxy never sees a partial file.
 *
 * @internal
 */
final readonly class ProxyCache
{
    private const string FUNCTIONS_DIR = '/_functions/';

    public function __construct(
        /** Cache manager, owns the cache directory and the file writer */
        private CachePathManager $cachePathManager,
        /** Aspect container, tells whether a cached function proxy is still fresh */
        private AspectContainer $container,
        /** Application root, source paths below it are kept relative in the cache */
        private string $appDir,
    ) {}

    /**
     * Saves the proxy of a woven class, returns the include statement of it, an empty string without a cache
     *
     * The proxy of the only woven class of a source file is named like that file. A file with several woven classes
     * gets one proxy per class below its name, used as a directory like a file inside a phar:
     * `src/Holder.php/App/First.php` (#760).
     */
    public function saveClassProxy(ReflectionClass $class, string $proxyCode, bool $isOnlyClass): string
    {
        $cacheRootDir = $this->cachePathManager->getCacheDir();
        if ($cacheRootDir === null) {
            return '';
        }
        $classFileName = $class->getFileName();
        if ($classFileName === false) {
            return '';
        }
        // Classes outside the application root keep their absolute path below the cache root
        $relativePath      = ltrim(PathResolver::rebase($classFileName, $this->appDir, '') ?? $classFileName, '/\\');
        $proxyRelativePath = str_replace('\\', '/', $relativePath);
        $sourceProxyPath   = $cacheRootDir . '/' . $proxyRelativePath;
        if (!$isOnlyClass) {
            $proxyRelativePath .= '/' . str_replace('\\', '/', $class->getName()) . '.php';
        }

        // The file switched between one and several woven classes since it was woven: drop the proxies of the other layout
        if ($isOnlyClass ? is_dir($sourceProxyPath) : is_file($sourceProxyPath)) {
            self::removePath($sourceProxyPath);
        }

        $this->cachePathManager->getCacheFileWriter()->write(
            $cacheRootDir . '/' . $proxyRelativePath,
            '<?php' . PHP_EOL . $proxyCode,
        );

        return self::includeStatement('/' . $proxyRelativePath);
    }

    /**
     * Saves the function proxies of a namespace unless a fresh one is cached, returns the include statement of it
     *
     * One file per namespace, shared by every source file declaring functions in it.
     *
     * @param Closure(): string $generateCode Generates the proxy code, called only when the cached one is stale
     *
     * @return string|null Null without a cache
     */
    public function saveFunctionProxy(string $namespaceName, Closure $generateCode): ?string
    {
        $cacheRootDir = $this->cachePathManager->getCacheDir();
        if ($cacheRootDir === null) {
            return null;
        }
        $proxyRelativePath = self::FUNCTIONS_DIR . str_replace('\\', '/', $namespaceName) . '.php';
        $proxyFileName     = $cacheRootDir . $proxyRelativePath;

        $filemtime = file_exists($proxyFileName) ? filemtime($proxyFileName) : false;
        if ($filemtime === false || !$this->container->isFreshSince($filemtime)) {
            $this->cachePathManager->getCacheFileWriter()->write($proxyFileName, $generateCode());
        }

        return self::includeStatement($proxyRelativePath);
    }

    private static function includeStatement(string $proxyRelativePath): string
    {
        return 'include_once AOP_CACHE_DIR . ' . var_export($proxyRelativePath, true) . ';';
    }

    /**
     * Removes a cache file or a cache directory with its content
     */
    private static function removePath(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);

            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDir()) {
                @rmdir($fileInfo->getPathname());
            } else {
                @unlink($fileInfo->getPathname());
            }
        }
        @rmdir($path);
    }
}
