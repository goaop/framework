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

namespace Go\Core;

use Go\Aop\Features;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 */
abstract class AspectKernel
{

    /**
     * Version of kernel
     */
    const VERSION = '2.1.0';

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
    protected static $containerClass = GoAspectContainer::class;

    /**
     * Flag to determine if kernel was already initialized or not
     *
     * @var bool
     */
    protected $wasInitialized = false;

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
     * @return static
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
    public function init(array $options = [])
    {
        if ($this->wasInitialized) {
            return;
        }

        $this->options = $this->normalizeOptions($options);
        define('AOP_ROOT_DIR', $this->options['appDir']);
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

        AopComposerLoader::init($this->options, $container);

        // Register all AOP configuration in the container
        $this->configureAop($container);

        $this->wasInitialized = true;
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
     *   cacheFileMode - integer Binary mask of permission bits that is set to cache files
     *   features - integer Binary mask of features
     *   includePaths - array Whitelist of directories where aspects should be applied. Empty for everywhere.
     *   excludePaths - array Blacklist of directories or files where aspects shouldn't be applied.
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return array(
            'debug'                  => false,
            'appDir'                 => __DIR__ . '/../../../../../',
            'cacheDir'               => null,
            'cacheFileMode'          => 0770 & ~umask(), // Respect user umask() policy
            'features'               => 0,

            'includePaths'           => [],
            'excludePaths'           => [],
            'containerClass'         => static::$containerClass,
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
        if ($options['cacheDir']) {
            $options['excludePaths'][] = $options['cacheDir'];
        }
        $options['excludePaths'][] = __DIR__ . '/../';

        $options['appDir']   = PathResolver::realpath($options['appDir']);
        $options['cacheDir'] = PathResolver::realpath($options['cacheDir']);
        $options['cacheFileMode'] = (int) $options['cacheFileMode'];
        $options['includePaths']  = PathResolver::realpath($options['includePaths']);
        $options['excludePaths']  = PathResolver::realpath($options['excludePaths']);

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
        $cacheManager     = $this->getContainer()->get('aspect.cache.path.manager');
        $filterInjector   = new FilterInjectorTransformer($this, SourceTransformingLoader::getId(), $cacheManager);
        $magicTransformer = new MagicConstantTransformer($this);
        $aspectKernel     = $this;

        $sourceTransformers = function () use ($filterInjector, $magicTransformer, $aspectKernel, $cacheManager) {
            $transformers    = [];
            $aspectContainer = $aspectKernel->getContainer();
            $transformers[]  = new WeavingTransformer(
                $aspectKernel,
                $aspectContainer->get('aspect.advice_matcher'),
                $cacheManager,
                $aspectContainer->get('aspect.cached.loader')
            );
            if ($aspectKernel->hasFeature(Features::INTERCEPT_INCLUDES)) {
                $transformers[] = $filterInjector;
            }
            $transformers[] = $magicTransformer;

            if ($aspectKernel->hasFeature(Features::INTERCEPT_INITIALIZATIONS)) {
                $transformers[] = new ConstructorExecutionTransformer();
            }

            return $transformers;
        };

        return array(
            new CachingTransformer($this, $sourceTransformers, $cacheManager)
        );
    }

    /**
     * Add resources for kernel
     *
     * @param AspectContainer $container
     */
    protected function addKernelResourcesToContainer(AspectContainer $container)
    {
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $refClass = new \ReflectionObject($this);

        $container->addResource($trace[1]['file']);
        $container->addResource($refClass->getFileName());
    }
}
