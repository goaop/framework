<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Features;
use Go\Aop\Pointcut;
use Go\Instrument\FileSystem\CacheFileWriter;
use ReflectionClass;
use Throwable;

/**
 * Cached loader is a decorator that is responsible for faster initialization
 * of pointcuts/advisors for concrete aspect
 *
 * Loaded advisors are compiled into plain-PHP cache files shadowing the aspect
 * sources: the aspect path below the application root is mirrored below the cache
 * directory with a '.cache.php' suffix, e.g. '{appDir}/src/Aspect/LoggingAspect.php'
 * is cached as '{cacheDir}/src/Aspect/LoggingAspect.cache.php'.
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class CachedAspectLoader implements AspectLoaderInterface
{
    /**
     * Original loader, resolved from the container on first access and memoized in the backing store
     */
    private AspectLoaderInterface $loader {
        get => $this->loader ??= $this->container->getService($this->loaderId);
    }

    /**
     * Path to the cache directory
     */
    private readonly ?string $cacheDir;

    /**
     * Path to the application root directory
     */
    private readonly string $appDir;

    /**
     * Whether an existing advisor cache file is trusted without freshness checks
     */
    private readonly bool $isPrebuiltCache;

    /**
     * Writer performing the actual cache file system operations
     */
    private readonly CacheFileWriter $cacheFileWriter;

    /**
     * Compiler rendering loaded advisors into plain-PHP cache file content
     */
    private readonly AdvisorCacheCompiler $advisorCacheCompiler;

    /**
     * @var class-string[] List of aspect class names that have been loaded
     */
    private array $loadedAspects = [];

    /**
     * Cached loader constructor
     *
     * @param class-string<AspectLoaderInterface> $loaderId Identifier of original loader
     * @phpstan-param KernelOptions $options List of kernel options
     */
    public function __construct(
        private readonly AspectContainer $container,
        private readonly string $loaderId,
        array $options,
    ) {
        $this->cacheDir             = $options['cacheDir'];
        $this->appDir               = $options['appDir'];
        $this->isPrebuiltCache      = ($options['features'] & Features::PREBUILT_CACHE) !== 0;
        $this->cacheFileWriter      = new CacheFileWriter($options['cacheFileMode']);
        $this->advisorCacheCompiler = new AdvisorCacheCompiler();
    }

    public function load(Aspect $aspect): array
    {
        if ($this->cacheDir === null || $this->cacheDir === '') {
            return $this->loader->load($aspect);
        }

        $refAspect      = new ReflectionClass($aspect);
        $aspectFileName = $refAspect->getFileName();
        $cacheFileName  = $aspectFileName !== false ? $this->resolveCacheFileName($aspectFileName) : null;
        if ($cacheFileName === null || $aspectFileName === false) {
            // Aspects without a resolvable source file below the application root are not cached
            return $this->loader->load($aspect);
        }

        // With a prebuilt cache an existing advisor cache file is trusted without any
        // freshness checks; on a corrupt/incompatible/empty file fall back to the direct
        // loader WITHOUT writing (the file system may be read-only).
        if ($this->isPrebuiltCache && file_exists($cacheFileName)) {
            $loadedItems = $this->loadFromCache($cacheFileName);
            if ($loadedItems !== []) {
                return $loadedItems;
            }

            return $this->loader->load($aspect);
        }

        // A fresh cache file is used only when it yields a usable result; a corrupt or
        // wrong-version file is rebuilt through the direct loader and rewritten below
        if (file_exists($cacheFileName) && filemtime($cacheFileName) >= filemtime($aspectFileName)) {
            $loadedItems = $this->loadFromCache($cacheFileName);
            if ($loadedItems !== []) {
                return $loadedItems;
            }
        }

        $loadedItems = $this->loader->load($aspect);
        $this->saveToCache($refAspect->getName(), $loadedItems, $cacheFileName);

        return $loadedItems;
    }

    /**
     * Loads and register all items of aspect in the container
     */
    public function loadAndRegister(Aspect $aspect): void
    {
        $loadedItems = $this->load($aspect);
        foreach ($loadedItems as $itemId => $item) {
            $this->container->add($itemId, $item);
        }
        $this->loadedAspects[$aspect::class] = $aspect::class;
    }

    /**
     * Returns list of unloaded aspects in the container
     *
     * @return list<Aspect>
     */
    public function getUnloadedAspects(): array
    {
        $unloadedAspects = [];

        foreach ($this->container->getServicesByInterface(Aspect::class) as $aspect) {
            if (!isset($this->loadedAspects[$aspect::class])) {
                $unloadedAspects[] = $aspect;
            }
        }

        return $unloadedAspects;
    }

    /**
     * Resolves the advisor cache file shadowing the given aspect source file
     *
     * @return string|null The cache file path, or null when the aspect source is not below the application root
     */
    private function resolveCacheFileName(string $aspectFileName): ?string
    {
        assert($this->cacheDir !== null);
        $shadowFileName = str_replace($this->appDir, $this->cacheDir, $aspectFileName);
        if ($shadowFileName === $aspectFileName) {
            return null;
        }

        if (str_ends_with($shadowFileName, '.php')) {
            $shadowFileName = substr($shadowFileName, 0, -strlen('.php'));
        }

        return $shadowFileName . '.cache.php';
    }

    /**
     * Loads pointcuts and advisors from the compiled cache file
     *
     * @return array<string, Pointcut|Advisor> Loaded items, or [] when the file is corrupt or incompatible
     */
    private function loadFromCache(string $fileName): array
    {
        try {
            $cacheData = (static fn(): mixed => include $fileName)();
        } catch (Throwable) {
            // A corrupt cache file is handled by the empty-result fallback
            return [];
        }

        if (
            !is_array($cacheData)
            || ($cacheData['version'] ?? null) !== AdvisorCacheCompiler::VERSION
            || !is_array($cacheData['advisors'] ?? null)
        ) {
            return [];
        }

        /** @var array<string, Pointcut|Advisor> $filtered */
        $filtered = array_filter(
            $cacheData['advisors'],
            fn($item) => $item instanceof Pointcut || $item instanceof Advisor,
        );

        return $filtered;
    }

    /**
     * Compiles pointcuts and advisors into the cache file
     *
     * Aspects holding items that cannot be expressed as plain PHP are simply not
     * cached at all - no file is written and the aspect is loaded directly each time.
     *
     * @param class-string                    $aspectClassName Aspect the items were loaded from
     * @param array<string, Pointcut|Advisor> $items           Array of items to store
     */
    private function saveToCache(string $aspectClassName, array $items, string $fileName): void
    {
        try {
            $content = $this->advisorCacheCompiler->compile($aspectClassName, $items);
        } catch (NotCompilableException) {
            return;
        }

        $this->cacheFileWriter->write($fileName, $content);
    }
}
