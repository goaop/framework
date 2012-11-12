<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

/**
 * Injects source filter for require and include operations
 *
 * @package go
 * @subpackage instrument
 */
class FilterInjectorTransformer implements SourceTransformer
{

    /**
     * Php filter definition
     */
    const PHP_FILTER_READ = 'php://filter/read=';

    /**
     * Root path of application
     *
     * This path will be replaced with rewriteToPath
     *
     * @var string
     */
    protected static $rootPath;

    /**
     * Path to rewrite for absolute address
     *
     * @var string
     */
    protected static $rewriteToPath;

    /**
     * Name of the filter to inject
     *
     * @var string
     */
    protected static $filterName;

    /**
     * Flag of configuration
     *
     * @var bool
     */
    protected static $configured = false;

    /**
     * Class constructor
     *
     * @param string $rootPath Path to the root of site
     * @param string $rewriteToPath Path to rewrite to (typically, this will be the cache)
     * @param string $filterName Name of the filter to inject
     */
    public function __construct($rootPath, $rewriteToPath, $filterName)
    {
        self::configure($rootPath, $rewriteToPath, $filterName);
    }

    /**
     * Static configurator for filter
     *
     * @param string $rootPath Path to the root of site
     * @param string $rewriteToPath Path to rewrite to (typically, this will be the cache)
     * @param string $filterName Name of the filter to inject
     */
    public static function configure($rootPath, $rewriteToPath, $filterName)
    {
        if (self::$configured) {
            throw new \RuntimeException("Filter injector can be configured only once.");
        }
        self::$rootPath      = realpath($rootPath);
        self::$rewriteToPath = realpath($rewriteToPath);
        self::$filterName    = $filterName;
        self::$configured    = true;
    }

    /**
     * Replace source path with correct one
     *
     * This operation can check for cache, can rewrite paths, add additional filters and much more
     *
     * @param string $resource Initial resource to include
     * @return string Transformed path to the resource
     */
    public static function rewrite($resource)
    {
        $relativeToRoot = stream_resolve_include_path($resource);
        if (strpos($relativeToRoot, self::$rootPath) === 0) {
            $relativeToRoot = substr($relativeToRoot, strlen(self::$rootPath));
            $relativeToRoot = self::$rewriteToPath . $relativeToRoot;
        }
        $newResource = $relativeToRoot;
        // TODO: add more accurate cache invalidation, like in Symfony2
        if (!file_exists($newResource) || filemtime($newResource) < filemtime($resource)) {
            @mkdir(dirname($newResource), 0770, true);
            $content = file_get_contents(self::PHP_FILTER_READ . self::$filterName . "/resource=" . $resource);
            file_put_contents($newResource, $content);
        }
        return $newResource;
    }

    /**
     * Wrap all includes into rewrite filter
     *
     * @param string $source Source for class
     * @param StreamMetaData $metadata Metadata for source
     *
     * @return string Transformed source
     */
    public function transform($source, StreamMetaData $metadata = null)
    {
        static $lookFor = array(T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE);
        $tokenStream = token_get_all($source);
        $transformedSource = '';
        $isWaitingEnd = false;
        foreach ($tokenStream as $token) {
            if ($isWaitingEnd && $token === ';') {
                $isWaitingEnd = false;
                $transformedSource .= ')';
            }
            $transformedSource .= (is_array($token) ? $token[1] : $token);
            if (!$isWaitingEnd && is_array($token) && in_array($token[0], $lookFor)) {
                $isWaitingEnd = true;
                $transformedSource  .= ' \\' . get_called_class() . '::rewrite(';
            }
        }
        return $transformedSource;
    }
}
