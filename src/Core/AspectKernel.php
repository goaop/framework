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

namespace Go\Core;

use Go\Aop\AspectException;
use Go\Aop\Features;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use RuntimeException;

use function define;

/**
 * Abstract aspect kernel is used to prepare an application to work with aspects.
 *
 * @phpstan-type KernelOptions array{
 *   debug: bool,
 *   appDir: string,
 *   cacheDir: string|null,
 *   cacheFileMode: int,
 *   features: int,
 *   includePaths: string[],
 *   excludePaths: string[],
 *   containerClass: class-string<AspectContainer>
 * }
 * @phpstan-type UserKernelOptions array{
 *   debug?: bool,
 *   appDir?: string,
 *   cacheDir?: string|null,
 *   cacheFileMode?: int,
 *   features?: int,
 *   includePaths?: string[],
 *   excludePaths?: string[],
 *   containerClass?: class-string<AspectContainer>
 * }
 */
abstract class AspectKernel
{
    /**
     * Kernel options
     *
     * @phpstan-var KernelOptions
     */
    protected array $options = [
        'debug'          => false,
        'appDir'         => '',
        'cacheDir'       => null,
        'cacheFileMode'  => 0,
        'features'       => 0,
        'includePaths'   => [],
        'excludePaths'   => [],
        'containerClass' => Container::class,
    ];

    /**
     * Single instance of kernel
     */
    protected static ?self $instance = null;

    /**
     * Default class name for container, can be redefined in children
     * @var class-string<AspectContainer>
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
     * Protected constructor is used to prevent direct creation of kernel
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
     * @phpstan-param UserKernelOptions $options Additional kernel options
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

        // The whole transformer pipeline (and the stream filter itself) is only needed on
        // a cache miss, so every transformer is registered as a typical deferred container
        // service and brought up by SourceTransformingLoader::ensureRegistered() from the
        // miss path. Caching itself lives in SourceTransformingLoader, which serves cache
        // hits before any transformer (or even the parser) is touched - the overridable
        // hook below only registers the transformation chain.
        $this->registerTransformerServices($container);

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
     *
     * @phpstan-return KernelOptions
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
     *
     * @phpstan-return KernelOptions
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
     * @param array<string, mixed> $options List of options
     * @phpstan-return KernelOptions
     */
    protected function normalizeOptions(array $options): array
    {
        $merged = [...$this->getDefaultOptions(), ...$options];

        $cacheDir = is_string($merged['cacheDir'] ?? null) ? $merged['cacheDir'] : null;
        if (empty($cacheDir)) {
            throw new RuntimeException('You need to provide valid cache directory for Go! AOP framework.');
        }

        $rawExcludePaths = is_array($merged['excludePaths'] ?? null) ? $merged['excludePaths'] : [];
        $excludePaths    = array_values(array_filter($rawExcludePaths, is_string(...)));
        $excludePaths[]  = $cacheDir;
        $excludePaths[]  = __DIR__ . '/../';

        $appDir        = is_string($merged['appDir'] ?? null) ? $merged['appDir'] : '';
        $cacheFileMode = is_int($merged['cacheFileMode'] ?? null) ? $merged['cacheFileMode'] : (0770 & ~umask());
        $features      = is_int($merged['features'] ?? null) ? $merged['features'] : 0;
        $rawIncludePaths = is_array($merged['includePaths'] ?? null) ? $merged['includePaths'] : [];
        $includePaths    = array_values(array_filter($rawIncludePaths, is_string(...)));
        $debug         = is_bool($merged['debug'] ?? null) ? $merged['debug'] : false;

        $containerClass       = static::$containerClass;
        $containerClassOption = $merged['containerClass'] ?? null;
        if (is_string($containerClassOption) && class_exists($containerClassOption)) {
            if (!is_a($containerClassOption, AspectContainer::class, true)) {
                throw new RuntimeException(sprintf(
                    'Container class "%s" must extend %s.',
                    $containerClassOption,
                    AspectContainer::class,
                ));
            }
            $containerClass = $containerClassOption;
        }

        $resolvedCacheDir = PathResolver::realpath($cacheDir);
        $resolvedCacheDir = is_string($resolvedCacheDir) ? $resolvedCacheDir : $cacheDir;

        $resolvedAppDir = PathResolver::realpath($appDir);
        $resolvedAppDir = is_string($resolvedAppDir) ? $resolvedAppDir : $appDir;

        $resolvedIncludePaths = PathResolver::realpath($includePaths);
        $resolvedExcludePaths = PathResolver::realpath($excludePaths);

        return [
            'debug'          => $debug,
            'appDir'         => $resolvedAppDir,
            'cacheDir'       => $resolvedCacheDir,
            'cacheFileMode'  => $cacheFileMode,
            'features'       => $features,
            'includePaths'   => array_values(array_filter($resolvedIncludePaths, is_string(...))),
            'excludePaths'   => array_values(array_filter($resolvedExcludePaths, is_string(...))),
            'containerClass' => $containerClass,
        ];
    }

    /**
     * Configures an AspectContainer with advisors, aspects and pointcuts
     */
    abstract protected function configureAop(AspectContainer $container): void;

    /**
     * Registers the source transformer services forming the transformation chain
     *
     * Every registered container service implementing SourceTransformer becomes part of
     * the chain, in registration order (registration order IS the transformation order);
     * feature flags gate registration exactly as they used to gate construction. Nothing
     * is constructed here - these are deferred definitions, materialized on the first
     * cache miss only.
     *
     * Override this method to replace, omit, reorder or extend the built-in transformers
     * (e.g. a mocking framework registering its own weaver instead of WeavingTransformer).
     * To merely append a transformer, a single addLazyService() call from configureAop()
     * is enough - it is picked up by the interface tag automatically.
     */
    protected function registerTransformerServices(AspectContainer $container): void
    {
        if ($this->hasFeature(Features::INTERCEPT_INITIALIZATIONS)) {
            $container->addLazyService(
                ConstructorExecutionTransformer::class,
                fn(): ConstructorExecutionTransformer => new ConstructorExecutionTransformer(),
            );
        }
        if ($this->hasFeature(Features::INTERCEPT_INCLUDES)) {
            $container->addLazyService(
                FilterInjectorTransformer::class,
                function (AspectContainer $container): FilterInjectorTransformer {
                    // Guarantees the stream filter exists even if this service is
                    // materialized directly, before the pipeline was brought up
                    SourceTransformingLoader::ensureRegistered($container);

                    return new FilterInjectorTransformer(
                        $this,
                        SourceTransformingLoader::getId(),
                        $container->getService(CachePathManager::class),
                    );
                },
            );
        }
        $container->addLazyService(
            WeavingTransformer::class,
            fn(AspectContainer $container): WeavingTransformer => new WeavingTransformer(
                $this,
                $container->getService(AdviceMatcher::class),
                $container->getService(CachePathManager::class),
                $container->getService(CachedAspectLoader::class),
            ),
        );
        $container->addLazyService(
            MagicConstantTransformer::class,
            fn(): MagicConstantTransformer => new MagicConstantTransformer($this),
        );
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
