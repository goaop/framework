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
    protected static $rewriteToPath = null;

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
     * Debug mode
     *
     * @var bool
     */
    protected static $debug = false;

    /**
     * Class constructor
     *
     * @param array $options Configuration options from kernel
     * @param string $filterName Name of the filter to inject
     */
    public function __construct(array $options, $filterName)
    {
        self::configure($options, $filterName);
    }

    /**
     * Static configurator for filter
     *
     * @param string $rootPath Path to the root of site
     * @param string $rewriteToPath Path to rewrite to (typically, this will be the cache)
     * @param string $filterName Name of the filter to inject
     */
    public static function configure(array $options, $filterName)
    {
        if (self::$configured) {
            throw new \RuntimeException("Filter injector can be configured only once.");
        }
        $rewriteToPath = $options['cacheDir'];
        if ($rewriteToPath) {
            self::$rewriteToPath = realpath($rewriteToPath);
        }
        self::$rootPath   = realpath($options['appDir']);
        self::$filterName = $filterName;
        self::$debug      = $options['debug'];
        self::$configured = true;
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
        // If cache is disabled, then use on-fly method
        if (!self::$rewriteToPath || self::$debug) {
            return self::PHP_FILTER_READ . self::$filterName . "/resource=" . $resource;
        }

        $newResource = stream_resolve_include_path($resource);
        $newResource = str_replace(self::$rootPath, self::$rewriteToPath, $newResource);

        // Trigger creation of cache, this will create a cache file with $newResource name
        if (!file_exists($newResource)) {
            file_get_contents(self::PHP_FILTER_READ . self::$filterName . "/resource=" . $resource);
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
        if ((strpos($source, 'include')===false) && (strpos($source, 'require')===false)) {
            return $source;
        }
        static $lookFor = array(
            T_INCLUDE      => true,
            T_INCLUDE_ONCE => true,
            T_REQUIRE      => true,
            T_REQUIRE_ONCE => true
        );
        $tokenStream       = token_get_all($source);

        $transformedSource = '';
        $isWaitingEnd      = false;
        foreach ($tokenStream as $token) {
            if ($isWaitingEnd && ($token === ';' || $token === ',')) {
                $isWaitingEnd = false;
                $transformedSource .= ')';
            }
            list ($token, $value) = (array) $token + array(1 => $token);
            $transformedSource .= $value;
            if (!$isWaitingEnd && isset($lookFor[$token])) {
                $isWaitingEnd = true;
                $transformedSource  .= ' \\' . __CLASS__ . '::rewrite(';
            }
        }
        return $transformedSource;
    }
}
