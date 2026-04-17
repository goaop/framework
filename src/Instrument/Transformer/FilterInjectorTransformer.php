<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Instrument\PathResolver;
use Go\Instrument\ClassLoading\CachePathManager;
use PhpParser\Node\Arg;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\NodeVisitorAbstract;
use RuntimeException;

/**
 * Transformer that injects source filter for "require" and "include" operations
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
class FilterInjectorTransformer extends NodeVisitorAbstract
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

    protected static ?AspectKernel $kernel = null;

    protected static ?CachePathManager $cachePathManager = null;
    private bool $hasChanges = false;

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel, string $filterName, CachePathManager $cacheManager)
    {
        self::configure($kernel, $filterName, $cacheManager);
    }

    /**
     * Static configurator for filter
     */
    protected static function configure(AspectKernel $kernel, string $filterName, CachePathManager $cacheManager): void
    {
        if (self::$kernel !== null) {
            throw new RuntimeException('Filter injector can be configured only once.');
        }
        self::$kernel           = $kernel;
        self::$options          = $kernel->getOptions();
        self::$filterName       = $filterName;
        self::$cachePathManager = $cacheManager;
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

    public function beforeTraverse(array $nodes): ?array
    {
        $this->hasChanges = false;

        return null;
    }

    /**
     * Wrap all includes into rewrite filter.
     */
    public function leaveNode(Node $node): ?Node
    {
        if (!$node instanceof Include_) {
            return null;
        }

        $this->hasChanges = true;
        $node->expr       = new StaticCall(
            new FullyQualified(self::class),
            'rewrite',
            [
                new Arg($node->expr),
                new Arg(new Dir()),
            ]
        );

        return $node;
    }

    public function hasChanges(): bool
    {
        return $this->hasChanges;
    }
}
