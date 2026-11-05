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
final class FilterInjectorTransformer implements PrefilteredNodeRewriter
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
     * `php://filter/read=<filter>/resource=`, built once the stream filter is up, see filterUriPrefix()
     */
    protected static ?string $filterUriPrefix = null;

    /**
     * Kernel options
     *
     * @phpstan-var KernelOptions
     */
    protected static array $options;

    protected static ?AspectKernel $kernel = null;

    protected static ?CachePathManager $cachePathManager = null;

    public array $nodeTypes {
        get => [Include_::class];
    }

    /**
     * include, include_once, require and require_once
     */
    public array $sourceMarkers {
        get => ['include', 'require'];
    }

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
    protected static function configure(AspectKernel $kernel, ?string $filterName, CachePathManager $cacheManager): void
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
        self::$filterUriPrefix  = null;
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
        // The stream filter is not registered here: an include served natively from the include map
        // (the warm production path) never needs it, see filterUriPrefix()
        self::configure(
            $kernel,
            null,
            $kernel->getContainer()->getService(CachePathManager::class),
        );
    }

    /**
     * Brings up the stream filter (and the transformer pipeline) on the first php://filter URI and remembers the URI
     * prefix: rewrite() reads {@see self::$filterUriPrefix} directly afterwards, without a call or a check
     */
    protected static function filterUriPrefix(): string
    {
        if (self::$filterName === null) {
            $kernel = self::$kernel ?? AspectKernel::getInstance();
            SourceTransformingLoader::ensureRegistered($kernel->getContainer());
            self::$filterName = SourceTransformingLoader::getId();
        }

        return self::$filterUriPrefix = self::PHP_FILTER_READ . self::$filterName . '/resource=';
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

        // In production a source known to the include map is included natively, like a class of the class map:
        // its cached file when it was woven, the source itself otherwise (no filter, no freshness check).
        // Records are keyed by resolved paths, so `__DIR__ . '/../file.php'` is resolved first
        if (!$debug && $cacheDir !== null && $cacheDir !== '') {
            $resolvedResource = str_contains($resource, '/.') ? PathResolver::realpath($resource) : $resource;
            $includeFile      = self::$cachePathManager?->findIncludeFile(
                is_string($resolvedResource) ? $resolvedResource : $resource,
            );
            if ($includeFile !== null) {
                return $includeFile;
            }
        }

        // A prebuilt cache knows every file it serves: an unknown one goes to the stream filter, which fails a source
        // of the cache before weaving it and serves any other file as it is (CachePathManager::$servesPrebuiltCache)
        if (self::$cachePathManager?->servesPrebuiltCache === true) {
            return (self::$filterUriPrefix ?? self::filterUriPrefix()) . $resource;
        }

        $cachedResource = self::$cachePathManager?->getCachePathForResource($resource);

        // If the cache is disabled, resource path not resolvable, or no cache yet, then use on-fly method
        if ($cachedResource === null || !$cacheDir || $debug || !file_exists($cachedResource)) {
            return (self::$filterUriPrefix ?? self::filterUriPrefix()) . $resource;
        }

        return $cachedResource;
    }

    /**
     * Wraps the include into rewrite filter
     */
    public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool
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
