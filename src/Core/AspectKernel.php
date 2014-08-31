<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Features;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\CleanableMemory;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use TokenReflection;

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 */
abstract class AspectKernel
{

    /**
     * Version of kernel
     */
    const VERSION = '0.4.3';

    /**
     * Kernel options
     *
     * @var array
     */
    protected $options = array(
        'features' => 0
    );

    /**
     * Single instance of kernel
     *
     * @var null|static
     */
    protected static $instance = null;

    /**
     * Default class name for container, can be redefined in children
     *
     * @var string
     */
    protected static $containerClass = 'Go\Core\GoAspectContainer';

    /**
     * Aspect container instance
     *
     * @var null|AspectContainer
     */
    protected $container = null;

    /**
     * Protected constructor is used to prevent direct creation, but allows customization if needed
     */
    protected function __construct() {}

    /**
     * Returns the single instance of kernel
     *
     * @return AspectKernel
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Init the kernel and make adjustments
     *
     * @param array $options Associative array of options for kernel
     */
    public function init(array $options = array())
    {
        $this->options = $this->normalizeOptions($options);
        define('AOP_CACHE_DIR', $this->options['cacheDir']);

        /** @var $container AspectContainer */
        $container = $this->container = new $this->options['containerClass'];
        $container->set('kernel', $this);
        $container->set('kernel.interceptFunctions', $this->hasFeature(Features::INTERCEPT_FUNCTIONS));
        $container->set('kernel.options', $this->options);

        SourceTransformingLoader::register();

        foreach ($this->registerTransformers() as $sourceTransformer) {
            SourceTransformingLoader::addTransformer($sourceTransformer);
        }

        // Register kernel resources in the container for debug mode
        if ($this->options['debug']) {
            $this->addKernelResourcesToContainer($container);
        }

        AopComposerLoader::init();

        // Register all AOP configuration in the container
        $this->configureAop($container);
    }

    /**
     * Returns an aspect container
     *
     * @return null|AspectContainer
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Returns a default bit mask of features by checking PHP version
     *
     * @return int
     */
    public static function getDefaultFeatures()
    {
        $features = 0;
        if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
            $features += Features::USE_CLOSURE;
            $features += Features::USE_TRAIT;
        }
        if (version_compare(PHP_VERSION, '5.5.0') >= 0) {
            $features += Features::USE_STATIC_FOR_LSB;
        }
        if (version_compare(PHP_VERSION, '5.6.0') >= 0) {
            $features += Features::USE_SPLAT_OPERATOR;
        }

        return $features;
    }

    /**
     * Checks if kernel configuration has enabled specific feature
     *
     * @param integer $featureToCheck See Go\Aop\Features enumeration class for features
     *
     * @return bool Whether specific feature enabled or not
     */
    public function hasFeature($featureToCheck)
    {
        return ($this->options['features'] & $featureToCheck) !== 0;
    }

    /**
     * Returns list of kernel options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Returns default options for kernel. Available options:
     *
     *   debug    - boolean Determines whether or not kernel is in debug mode
     *   appDir   - string Path to the application root directory.
     *   cacheDir - string Path to the cache directory where compiled classes will be stored
     *   features - integer Binary mask of features
     *   includePaths - array Whitelist of directories where aspects should be applied. Empty for everywhere.
     *   excludePaths - array Blacklist of directories or files where aspects shouldn't be applied.
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        $features = static::getDefaultFeatures();

        return array(
            'debug'     => false,
            'appDir'    => __DIR__ . '/../../../../../../',
            'cacheDir'  => null,
            'features' => $features,

            'includePaths'       => array(),
            'excludePaths'       => array(),
            'containerClass'     => static::$containerClass,
        );
    }


    /**
     * Normalizes options for the kernel
     *
     * @param array $options List of options
     *
     * @return array
     */
    protected function normalizeOptions(array $options)
    {
        $options = array_replace($this->getDefaultOptions(), $options);

        $options['appDir']   = PathResolver::realpath($options['appDir']);
        $options['cacheDir'] = PathResolver::realpath($options['cacheDir']);
        $options['includePaths'] = PathResolver::realpath($options['includePaths']);
        $options['excludePaths'] = PathResolver::realpath($options['excludePaths']);

        return $options;
    }

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    abstract protected function configureAop(AspectContainer $container);

    /**
     * Returns list of source transformers, that will be applied to the PHP source
     *
     * @return array|SourceTransformer[]
     */
    protected function registerTransformers()
    {
        $filterInjector   = new FilterInjectorTransformer($this->options, SourceTransformingLoader::getId());
        $magicTransformer = new MagicConstantTransformer($this);
        $aspectKernel     = $this;

        $sourceTransformers = function () use ($filterInjector, $magicTransformer, $aspectKernel) {
            return array(
                $filterInjector,
                $magicTransformer,
                new WeavingTransformer(
                    $aspectKernel,
                    new TokenReflection\Broker(
                        new CleanableMemory()
                    ),
                    $aspectKernel->getContainer()->get('aspect.advice_matcher')
                )
            );
        };

        return array(
            new CachingTransformer($this, $sourceTransformers)
        );
    }

    /**
     * Add resources for kernel
     *
     * @param AspectContainer $container
     */
    protected function addKernelResourcesToContainer(AspectContainer $container)
    {
        $trace    = debug_backtrace();
        $refClass = new \ReflectionObject($this);

        $container->addResource($trace[1]['file']);
        $container->addResource($refClass->getFileName());
    }
}
