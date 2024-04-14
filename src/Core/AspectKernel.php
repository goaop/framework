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

use Go\Aop\AspectException;
use Go\Aop\Features;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\CachingTransformer;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\SelfValueTransformer;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
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
     * @var class-string
     */
    protected static string $containerClass = Container::class;

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
     * @param array{
     *   debug?: bool,
     *   appDir?: literal-string&non-falsy-string,
     *   cacheDir?: string|null,
     *   cacheFileMode?: int,
     *   features?: int,
     *   includePaths?: array{},
     *   excludePaths?: array{},
     *   containerClass?: class-string
     * } $options Additional kernel options
     */
    public function init(array $options = []): void
    {
        if ($this->wasInitialized) {
            return;
        }

        $this->options = $this->normalizeOptions($options);
        define('AOP_ROOT_DIR', $this->options['appDir']);
        define('AOP_CACHE_DIR', $this->options['cacheDir']);

        $resourcesToTrack = [];
        if ($this->options['debug']) {
            $resourcesToTrack[] = $this->getFileNameWhereInitialized();
        }

        if (!is_subclass_of($this->options['containerClass'], AspectContainer::class)) {
            throw new AspectException("Invalid aspect container class");
        }

        $container = $this->container = new $this->options['containerClass']($resourcesToTrack);
        $container->add(AspectKernel::class, $this);
        $container->add('kernel.interceptFunctions', $this->hasFeature(Features::INTERCEPT_FUNCTIONS));
        $container->add('kernel.options', $this->options);

        SourceTransformingLoader::register();

        foreach ($this->registerTransformers() as $sourceTransformer) {
            SourceTransformingLoader::addTransformer($sourceTransformer);
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
        $options = [...$this->getDefaultOptions(), ...$options];

        if (empty($options['cacheDir'])) {
            throw new RuntimeException('You need to provide valid cache directory for Go! AOP framework.');
        }
        $options['cacheDir']       = PathResolver::realpath($options['cacheDir']);
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
        $cacheManager     = $this->getContainer()->getService(CachePathManager::class);
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
                $this->container->getService(AdviceMatcher::class),
                $cacheManager,
                $this->container->getService(CachedAspectLoader::class)
            );
            $transformers[] = $magicTransformer;

            return $transformers;
        };

        return [
            new CachingTransformer($this, $sourceTransformers, $cacheManager)
        ];
    }

    /**
     * Returns a file name where kernel has been initialized
     */
    final protected function getFileNameWhereInitialized(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        assert(isset($trace[1]['file']), "There should be at least 2 stack frames here");

        return $trace[1]['file'];
    }
}
