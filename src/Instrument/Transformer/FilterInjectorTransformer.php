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
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeTraverser;
use RuntimeException;

/**
 * Transformer that injects source filter for "require" and "include" operations
 */
class FilterInjectorTransformer implements SourceTransformer
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
     */
    protected static array $options = [];

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
        $cachedResource = self::$cachePathManager->getCachePathForResource($resource);

        // If the cache is disabled or no cache yet, then use on-fly method
        if (!$cacheDir || $debug || !file_exists($cachedResource)) {
            return self::PHP_FILTER_READ . self::$filterName . '/resource=' . $resource;
        }

        return $cachedResource;
    }

    /**
     * Wrap all includes into rewrite filter
     *
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata): string
    {
        $includeExpressionFinder = new NodeFinderVisitor([Include_::class]);

        // TODO: move this logic into walkSyntaxTree(Visitor $nodeVistor) method
        $traverser = new NodeTraverser();
        $traverser->addVisitor($includeExpressionFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var Include_[] $includeExpressions */
        $includeExpressions = $includeExpressionFinder->getFoundNodes();

        if (empty($includeExpressions)) {
            return self::RESULT_ABSTAIN;
        }

        foreach ($includeExpressions as $includeExpression) {
            $startPosition = $includeExpression->getAttribute('startTokenPos');
            $endPosition   = $includeExpression->getAttribute('endTokenPos');

            $metadata->tokenStream[$startPosition]->text .= ' \\' . self::class . '::rewrite(';
            if ($metadata->tokenStream[$startPosition+1]->id === T_WHITESPACE) {
                unset($metadata->tokenStream[$startPosition+1]);
            }

            $metadata->tokenStream[$endPosition]->text .= ', __DIR__)';
        }

        return self::RESULT_TRANSFORMED;
    }
}
