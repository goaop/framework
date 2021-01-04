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
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\SelfValueTransformer;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use ReflectionObject;
use RuntimeException;

use function define;

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 */
abstract class AspectKernel
{
    /**
     * Kernel options
     */
    protected array $options = [
        'features' => 0
    ];

    /**
     * Single instance of kernel
     */
    protected static ?self $instance = null;

    /**
     * Default class name for container, can be redefined in children
     */
    protected static string $containerClass = GoAspectContainer::class;

    /**
     * Flag to determine if kernel was already initialized or not
     */
    protected bool $wasInitialized = false;

    /**
     * Aspect container instance
     */
    protected AspectContainer $container;

    /**
     * Protected constructor is used to prevent direct creation, but allows customization if needed
     */
    final protected function __construct() {}

    /**
     * Returns the single instance of kernel
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            // PhpStan complains about LSB and args for constructor, so constructor should be final
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Init the kernel and make adjustments
     *
     * @param array $options Associative array of options for kernel
     */
    public function init(array $options = []): void
    {
        if ($this->wasInitialized) {
            return;
        }

        $this->options = $this->normalizeOptions($options);
        define('AOP_ROOT_DIR', $this->options['appDir']);
        define('AOP_CACHE_DIR', $this->options['cacheDir']);

        /** @var AspectContainer $container */
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
     */
    public function getContainer(): AspectContainer
    {
        return $this->container;
    }

    /**
     * Checks if kernel configuration has enabled specific feature
     *
     * @see \Go\Aop\Features enumeration class for features
     */
    public function hasFeature(int $featureToCheck): bool
    {
        return ($this->options['features'] & $featureToCheck) !== 0;
    }

    /**
     * Returns list of kernel options
     */
    public function getOptions(): array
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
     *   annotationCache - Doctrine\Common\Cache\Cache. If not provided, Doctrine\Common\Cache\PhpFileCache is used.
     *   features - integer Binary mask of features
     *   includePaths - array Whitelist of directories where aspects should be applied. Empty for everywhere.
     *   excludePaths - array Blacklist of directories or files where aspects shouldn't be applied.
     */
    protected function getDefaultOptions(): array
    {
        return [
            'debug'           => false,
            'appDir'          => __DIR__ . '/../../../../../',
            'cacheDir'        => null,
            'cacheFileMode'   => 0770 & ~umask(), // Respect user umask() policy
            'features'        => 0,
            'annotationCache' => null,
            'includePaths'    => [],
            'excludePaths'    => [],
            'containerClass'  => static::$containerClass,
        ];
    }


    /**
     * Normalizes options for the kernel
     *
     * @param array $options List of options
     */
    protected function normalizeOptions(array $options): array
    {
        $options = array_replace($this->getDefaultOptions(), $options);

        $options['cacheDir'] = PathResolver::realpath($options['cacheDir']);

        if ($options['cacheDir'] === []) {
            throw new RuntimeException('You need to provide valid cache directory for Go! AOP framework.');
        }

        $options['excludePaths'][] = $options['cacheDir'];
        $options['excludePaths'][] = __DIR__ . '/../';
        $options['appDir']         = PathResolver::realpath($options['appDir']);
        $options['cacheFileMode']  = (int) $options['cacheFileMode'];
        $options['includePaths']   = PathResolver::realpath($options['includePaths']);
        $options['excludePaths']   = PathResolver::realpath($options['excludePaths']);

        return $options;
    }

    /**
     * Configures an AspectContainer with advisors, aspects and pointcuts
     */
    abstract protected function configureAop(AspectContainer $container);

    /**
     * Returns list of source transformers, that will be applied to the PHP source
     *
     * @return SourceTransformer[]
     * @internal This method is internal and should not be used outside this project
     */
    protected function registerTransformers(): array
    {
        $cacheManager     = $this->getContainer()->get('aspect.cache.path.manager');
        $filterInjector   = new FilterInjectorTransformer($this, SourceTransformingLoader::getId(), $cacheManager);
        $magicTransformer = new MagicConstantTransformer($this);

        $sourceTransformers = function () use ($filterInjector, $magicTransformer, $cacheManager) {
            $transformers = [];
            if ($this->hasFeature(Features::INTERCEPT_INITIALIZATIONS)) {
                $transformers[] = new ConstructorExecutionTransformer();
            }
            if ($this->hasFeature(Features::INTERCEPT_INCLUDES)) {
                $transformers[] = $filterInjector;
            }
            $transformers[]  = new SelfValueTransformer($this);
            $transformers[]  = new WeavingTransformer(
                $this,
                $this->container->get('aspect.advice_matcher'),
                $cacheManager,
                $this->container->get('aspect.cached.loader')
            );
            $transformers[] = $magicTransformer;

            return $transformers;
        };

        return [
            new CachingTransformer($this, $sourceTransformers, $cacheManager)
        ];
    }

    /**
     * Add resources of kernel to the container
     */
    protected function addKernelResourcesToContainer(AspectContainer $container): void
    {
        $trace    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $refClass = new ReflectionObject($this);

        $container->addResource($trace[1]['file']);
        $container->addResource($refClass->getFileName());
    }
}
