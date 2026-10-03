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

use Composer\InstalledVersions;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Features;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\AopComposerLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\PathResolver;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
use ReflectionClass;

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
     * Composer packages the framework runs on during weaving, always excluded from weaving
     */
    private const array RUNTIME_DEPENDENCIES = [
        'goaop/dissect',
        'goaop/parser-reflection',
        'nikic/php-parser',
        'symfony/finder',
    ];

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
     *
     * @throws AspectException When called on an abstract kernel before the application kernel was created
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            if (new ReflectionClass(static::class)->isAbstract()) {
                throw new AspectException('Aspect kernel is not initialized yet, call init() on the kernel of the application first');
            }
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
        if (!defined('AOP_ROOT_DIR')) {
            define('AOP_ROOT_DIR', $this->options['appDir']);
        }
        if (!defined('AOP_CACHE_DIR')) {
            define('AOP_CACHE_DIR', $this->options['cacheDir']);
        }

        $resourcesToTrack = [];
        if ($this->options['debug']) {
            $resourcesToTrack[] = $this->getFileNameWhereInitialized();
        }

        $container = $this->container = new $this->options['containerClass']($resourcesToTrack);
        $container->add(AspectKernel::class, $this);
        $container->add('kernel.interceptFunctions', $this->hasFeature(Features::INTERCEPT_FUNCTIONS));

        // The framework's own services are deferred definitions registered through the
        // generic lazy container API - the container itself knows nothing about them.
        FrameworkServices::register($container);

        // The whole transformer pipeline (and the stream filter itself) is only needed on
        // a cache miss, so every transformer is registered as a typical deferred container
        // service and brought up by SourceTransformingLoader::ensureRegistered() from the
        // miss path. Caching itself lives in SourceTransformingLoader, which serves cache
        // hits before any transformer (or even the parser) is touched - the overridable
        // hook below only registers the transformation chain.
        $this->registerTransformerServices($container);

        AopComposerLoader::init($this->options, $container);

        // In debug mode every lazily registered aspect's source file must be tracked as a
        // resource right away: SourceTransformingLoader consults resource freshness before
        // any aspect materializes. Production arms no listener - registration stays a pure
        // array write, its warm path never checks freshness, and a cache miss materializes
        // every aspect during weaving anyway. Armed after the framework/transformer
        // services above, so only aspects from configureAop() pass through it.
        if ($this->options['debug']) {
            $container->onRegistration(Aspect::class, static function (string $aspectClassName) use ($container): void {
                $aspectFileName = (new ReflectionClass($aspectClassName))->getFileName();
                if (is_string($aspectFileName)) {
                    $container->addResource($aspectFileName);
                }
            });
        }

        // Register all AOP configuration in the container
        $this->configureAop($container);

        $this->wasInitialized = true;
    }

    /**
     * Returns an aspect container
     */
    public function getContainer(): AspectContainer
    {
        if (!isset($this->container)) {
            throw new AspectException(static::class . ' is not initialized yet, call init() first');
        }

        return $this->container;
    }

    /**
     * Checks if kernel configuration has enabled specific feature
     *
     * @see \Go\Aop\Features enumeration class for features
     */
    public function hasFeature(int $featureToCheck): bool
    {
        if ($featureToCheck === 0 || ($featureToCheck & ~Features::ALL) !== 0) {
            throw new InvalidConfigurationException(sprintf('Unknown feature %d, use Go\\Aop\\Features constants', $featureToCheck));
        }

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
     *   cacheFileMode - integer Binary mask of permission bits that is set to cache files (0600..0777, owner must
     *                   be able to read and write; directories get the matching search bits)
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
        $defaultOptions = $this->getDefaultOptions();
        $unknownOptions = array_diff_key($options, $defaultOptions);
        foreach (array_keys($unknownOptions) as $unknownOption) {
            $suggestion = null;
            foreach (array_keys($defaultOptions) as $knownOption) {
                if (levenshtein(strtolower((string) $unknownOption), strtolower($knownOption)) <= 3) {
                    $suggestion = $knownOption;
                    break;
                }
            }
            throw new InvalidConfigurationException(sprintf(
                'Unknown kernel option "%s"%s. Known options are: %s.',
                $unknownOption,
                $suggestion !== null ? sprintf(', did you mean "%s"?', $suggestion) : '',
                implode(', ', array_keys($defaultOptions)),
            ));
        }
        $merged = [...$defaultOptions, ...$options];

        $cacheDir = is_string($merged['cacheDir'] ?? null) ? $merged['cacheDir'] : null;
        if ($cacheDir === null || $cacheDir === '') {
            throw new InvalidConfigurationException('You need to provide valid cache directory for Go! AOP framework.');
        }

        $rawExcludePaths = is_array($merged['excludePaths'] ?? null) ? $merged['excludePaths'] : [];
        $excludePaths    = array_values(array_filter($rawExcludePaths, is_string(...)));
        $excludePaths[]  = $cacheDir;
        $excludePaths[]  = __DIR__ . '/../';
        foreach (self::RUNTIME_DEPENDENCIES as $dependency) {
            // The weaver must never weave the code it runs on: exclude the installed runtime dependencies
            $installPath = InstalledVersions::isInstalled($dependency) ? InstalledVersions::getInstallPath($dependency) : null;
            $installPath = $installPath !== null ? realpath($installPath) : false;
            if ($installPath !== false) {
                $excludePaths[] = $installPath;
            }
        }

        $appDir        = is_string($merged['appDir'] ?? null) ? $merged['appDir'] : '';
        $cacheFileMode = $merged['cacheFileMode'] ?? null;
        if ($cacheFileMode === null) {
            $cacheFileMode = 0770 & ~umask();
        } elseif (!is_int($cacheFileMode) || $cacheFileMode < 0 || $cacheFileMode > 0777 || ($cacheFileMode & 0600) !== 0600) {
            throw new InvalidConfigurationException(sprintf(
                'Option "cacheFileMode" must be an integer permission mask between 0600 and 0777 '
                . 'that grants the owner read and write access, got %s.',
                is_int($cacheFileMode) ? sprintf('0%o', $cacheFileMode) : get_debug_type($cacheFileMode),
            ));
        }
        $features = $merged['features'] ?? 0;
        if (!is_int($features) || ($features & ~Features::ALL) !== 0) {
            throw new InvalidConfigurationException(sprintf(
                'Option "features" must be a combination of Go\\Aop\\Features constants, got %s.',
                is_int($features) ? (string) $features : get_debug_type($features),
            ));
        }
        $rawIncludePaths = is_array($merged['includePaths'] ?? null) ? $merged['includePaths'] : [];
        $includePaths    = array_values(array_filter($rawIncludePaths, is_string(...)));
        $debug         = is_bool($merged['debug'] ?? null) ? $merged['debug'] : false;

        $containerClass       = static::$containerClass;
        $containerClassOption = $merged['containerClass'] ?? null;
        if ($containerClassOption !== null) {
            if (!is_string($containerClassOption) || !class_exists($containerClassOption)) {
                throw new InvalidConfigurationException(sprintf(
                    'Container class %s does not exist.',
                    is_string($containerClassOption) ? '"' . $containerClassOption . '"' : get_debug_type($containerClassOption),
                ));
            }
            if (!is_a($containerClassOption, AspectContainer::class, true)) {
                throw new InvalidConfigurationException(sprintf(
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
