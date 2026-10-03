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

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Instrument\PathResolver;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;

/**
 * Rule that injects source filter for "require" and "include" operations
 *
 * @phpstan-import-type KernelOptions from AspectKernel
 */
final class FilterInjectorTransformer implements NodeRewriter
{
    /**
     * Php filter definition
     */
    public const string PHP_FILTER_READ = 'php://filter/read=';

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

    /**
     * Class constructor
     */
    public function __construct(AspectKernel $kernel, string $filterName, CachePathManager $cacheManager)
    {
        self::configure($kernel, $filterName, $cacheManager);
    }

    /**
     * Static configurator for filter
     *
     * Set-once: the first configuration wins. The transformer can be configured either
     * through its constructor (weaving path) or implicitly by the first rewrite() call.
     */
    protected static function configure(AspectKernel $kernel, string $filterName, CachePathManager $cacheManager): void
    {
        if (self::$kernel !== null) {
            return;
        }
        self::$kernel           = $kernel;
        self::$options          = $kernel->getOptions();
        self::$filterName       = $filterName;
        self::$cachePathManager = $cacheManager;
    }

    /**
     * Forgets the configuration, the next rewrite() configures from the booted kernel again
     *
     * @internal For tests and processes that boot the framework again
     */
    public static function reset(): void
    {
        self::$kernel           = null;
        self::$filterName       = null;
        self::$cachePathManager = null;
    }

    /**
     * Configures the rewriting statics on demand from the booted kernel
     *
     * rewrite() call sites (autoloader miss path, rewritten include statements inside
     * cached files) can run before any transformer object exists, since the pipeline
     * itself is constructed lazily on the first cache miss.
     */
    protected static function ensureConfigured(): void
    {
        if (self::$kernel !== null) {
            return;
        }
        $kernel = AspectKernel::getInstance();
        // Bring up the stream filter (and the transformer pipeline) so that the
        // php://filter fallback URI built below is actually serviceable
        SourceTransformingLoader::ensureRegistered($kernel->getContainer());
        self::configure(
            $kernel,
            SourceTransformingLoader::getId(),
            $kernel->getContainer()->getService(CachePathManager::class),
        );
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
        self::ensureConfigured();

        $cacheDir = self::$options['cacheDir'];
        $debug    = self::$options['debug'];

        $resource = $originalResource;
        if ($resource[0] !== '/') {
            $shouldCheckExistence = true;
            $resource
                =  PathResolver::realpath($resource, $shouldCheckExistence)
                ?: PathResolver::realpath("{$originalDir}/{$resource}", $shouldCheckExistence)
                ?: $originalResource;
        }
        $cachedResource = self::$cachePathManager?->getCachePathForResource($resource);

        // If the cache is disabled, resource path not resolvable, or no cache yet, then use on-fly method
        if ($cachedResource === null || !$cacheDir || $debug || !file_exists($cachedResource)) {
            return self::PHP_FILTER_READ . self::$filterName . '/resource=' . $resource;
        }

        return $cachedResource;
    }

    public function getNodeTypes(): array
    {
        return [Include_::class];
    }

    /**
     * Wraps the include into rewrite filter
     */
    public function rewriteNode(Node $node, StreamMetaData $file): bool
    {
        $startPosition = $node->getAttribute('startTokenPos');
        $endPosition   = $node->getAttribute('endTokenPos');
        if (!$node instanceof Include_ || !is_int($startPosition) || !is_int($endPosition)) {
            return false;
        }

        $file->tokenStream[$startPosition]->text .= ' \\' . self::class . '::rewrite(';
        if ($file->tokenStream[$startPosition + 1]->id === T_WHITESPACE) {
            unset($file->tokenStream[$startPosition + 1]);
        }

        $file->tokenStream[$endPosition]->text .= ', __DIR__)';

        return true;
    }
}
