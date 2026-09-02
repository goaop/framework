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

use Closure;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Instrument\ClassLoading\CachePathManager;
use OutOfBoundsException;
use ReflectionClass;
use ReflectionObject;

/**
 * DI-container
 */
class Container implements AspectContainer
{
    /**
     * @var array<string, mixed> Hashmap of items/services in the container
     */
    private array $values = [];

    /**
     * @var array<class-string, Closure(AspectContainer): object> Deferred service factories, keyed by class-name
     */
    private array $factories = [];

    /**
     * @var array<class-string, Closure(): void> Optional eager validators for deferred services, run when the
     *      lazy object is created (first retrieval) - before the factory itself runs (first actual use)
     */
    private array $factoryValidators = [];

    /**
     * @var array<class-string, list<string>> Holds information about mapping of interface tags into identifiers
     */
    private array $tags = [];

    /**
     * Cached timestamp for resources, might be uninitialized if {@see self::isFreshSince()} is not called yet
     */
    private int $cachedMaxTimestamp;

    /**
     * @var array<string, string> Hashmap of resources for application
     */
    private array $resources;

    /**
     * Constructor for container
     *
     * @param list<string> $resources [Optional] List of additional resources to track for container invalidation
     */
    public function __construct(array $resources = [])
    {
        $this->resources = array_combine($resources, $resources);

        $this->addLazyService(PointcutLexer::class, fn(): PointcutLexer => new PointcutLexer());

        $this->addLazyService(PointcutParser::class, fn(): PointcutParser => new PointcutParser(
            new PointcutGrammar(),
        ));

        $this->addLazyService(AdviceMatcher::class, fn(AspectContainer $container): AdviceMatcher => new AdviceMatcher(
            (bool) $container->getValue('kernel.interceptFunctions'),
        ));

        $this->addLazyService(AttributeAspectLoaderExtension::class, fn(AspectContainer $container): AttributeAspectLoaderExtension => new AttributeAspectLoaderExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class),
        ));

        $this->addLazyService(IntroductionAspectExtension::class, fn(AspectContainer $container): IntroductionAspectExtension => new IntroductionAspectExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class),
        ));

        $this->addLazyService(AspectLoader::class, fn(AspectContainer $container): AspectLoader => new AspectLoader(
            $container,
            $container->getService(AttributeAspectLoaderExtension::class),
            $container->getService(IntroductionAspectExtension::class),
        ));

        $this->addLazyService(CachedAspectLoader::class, function (AspectContainer $container): CachedAspectLoader {
            $options = $container->getService(AspectKernel::class)->getOptions();

            return new CachedAspectLoader($container, AspectLoader::class, $options);
        });

        $this->addLazyService(CachePathManager::class, fn(AspectContainer $container): CachePathManager => new CachePathManager(
            $container->getService(AspectKernel::class),
        ));
    }

    final public function registerAspect(Aspect|string $aspectOrClassName, ?Closure $aspectFactory = null): void
    {
        if ($aspectOrClassName instanceof Aspect) {
            $this->add($aspectOrClassName::class, $aspectOrClassName);

            return;
        }

        // Deferred registration by class-name: the aspect is constructed on first use
        // (first advice hit, or first real interaction with the lazy object handed out
        // on the weaving path), so a hot-cache request never pays for aspects it does
        // not touch.
        $this->addLazyService($aspectOrClassName, function () use ($aspectOrClassName, $aspectFactory): Aspect {
            return $this->materializeAspect($aspectOrClassName, $aspectFactory);
        });

        // Cheap aspect declaration checks (implements Aspect, constructibility) run as soon
        // as the service materializes into a lazy object, so misconfiguration surfaces on
        // retrieval - construction itself stays deferred until first actual use.
        $this->factoryValidators[$aspectOrClassName] = function () use ($aspectOrClassName, $aspectFactory): void {
            $this->validateAspectRegistration($aspectOrClassName, $aspectFactory);
        };

        // In debug mode the aspect's source file must be tracked as a resource right away:
        // SourceTransformingLoader consults resource freshness before any aspect materializes.
        // Production skips this - its warm path never checks freshness, and a cache miss
        // materializes every aspect during weaving anyway.
        if ($this->isDebug()) {
            if (!is_subclass_of($aspectOrClassName, Aspect::class)) {
                throw new AspectException("Aspect class $aspectOrClassName must implement " . Aspect::class);
            }
            $aspectFileName = (new ReflectionClass($aspectOrClassName))->getFileName();
            if (is_string($aspectFileName)) {
                $this->addResource($aspectFileName);
            }
        }
    }

    /**
     * Constructs a lazily registered aspect, either through its factory or by validated
     * default construction
     *
     * @param null|Closure(AspectContainer): Aspect $aspectFactory
     */
    private function materializeAspect(string $aspectClassName, ?Closure $aspectFactory): Aspect
    {
        $this->validateAspectRegistration($aspectClassName, $aspectFactory);
        assert(is_subclass_of($aspectClassName, Aspect::class));

        if ($aspectFactory !== null) {
            $aspect = $aspectFactory($this);
            if (!$aspect instanceof $aspectClassName) {
                throw new AspectException("Aspect factory for $aspectClassName returned an incompatible object");
            }

            return $aspect;
        }

        return new $aspectClassName();
    }

    /**
     * Validates a deferred aspect registration without constructing the aspect
     *
     * @param null|Closure(AspectContainer): Aspect $aspectFactory
     *
     * @throws AspectException if the class is not an aspect or cannot be default-constructed
     */
    private function validateAspectRegistration(string $aspectClassName, ?Closure $aspectFactory): void
    {
        if (!is_subclass_of($aspectClassName, Aspect::class)) {
            throw new AspectException("Aspect class $aspectClassName must implement " . Aspect::class);
        }
        if ($aspectFactory === null) {
            $constructor = (new ReflectionClass($aspectClassName))->getConstructor();
            if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new AspectException(
                    "Aspect $aspectClassName has required constructor arguments, "
                    . "pass a factory closure to registerAspect() to create it",
                );
            }
        }
    }

    /**
     * Whether the kernel that owns this container runs in debug mode
     */
    private function isDebug(): bool
    {
        if (!$this->has('kernel.options')) {
            return false;
        }
        $options = $this->getValue('kernel.options');

        return is_array($options) && ($options['debug'] ?? false) === true;
    }

    final public function add(string $id, mixed $value): void
    {
        $this->values[$id] = $value;

        if (is_object($value) && !$value instanceof Closure) {
            $reflectionInstance = new ReflectionObject($value);
            foreach ($reflectionInstance->getInterfaceNames() as $interfaceTagName) {
                $this->tags[$interfaceTagName][] = $id;
            }
            $fileName = $reflectionInstance->getFileName();
            if (is_string($fileName)) {
                $this->addResource($fileName);
            }
        }
    }

    final public function addLazyService(string $id, Closure $lazyInitializationClosure): void
    {
        // Only class-names are acceptable ids here: getServicesByInterface() probes these
        // keys with is_subclass_of(), so an arbitrary string id must be rejected upfront
        // (checked syntactically to avoid autoloading anything at registration time).
        if (preg_match('/^\\\\?[A-Za-z_\x80-\xff][\w\x80-\xff]*(\\\\[A-Za-z_\x80-\xff][\w\x80-\xff]*)*$/', $id) !== 1) {
            throw new AspectException("Lazy service id must be a valid class name, \"$id\" given");
        }
        $this->factories[$id] = $lazyInitializationClosure;
        unset($this->factoryValidators[$id]);
    }

    final public function getService(string $className): object
    {
        if (!isset($this->values[$className]) && isset($this->factories[$className])) {
            $this->materializeService($className);
        }
        if (!isset($this->values[$className])) {
            throw new OutOfBoundsException("Value $className is not defined in the container");
        }
        if (!$this->values[$className] instanceof $className) {
            throw new AspectException("Service $className is not properly registered");
        }

        return $this->values[$className];
    }

    final public function getValue(string $key): mixed
    {
        if (!isset($this->values[$key])) {
            if (isset($this->factories[$key])) {
                $this->materializeService($key);
            } else {
                throw new OutOfBoundsException("Value $key is not defined in the container");
            }
        }

        return $this->values[$key];
    }

    final public function has(string $id): bool
    {
        return isset($this->values[$id]) || isset($this->factories[$id]);
    }

    final public function getServicesByInterface(string $interfaceTagClassName): array
    {
        // Deferred services are only tagged once materialized (as lazy objects), so
        // materialize the pending ones that implement the requested interface first.
        // This path is only taken during weaving/console runs, never on a hot request.
        // The is_subclass_of() autoload (and an eager fallback factory) can re-enter
        // this method (an aspect class autoloaded here goes through the weaving
        // pipeline, which enumerates aspects again), consuming pending factories from
        // under this loop - hence the existence re-check and the tolerant materialization.
        foreach (array_keys($this->factories) as $id) {
            if (array_key_exists($id, $this->factories) && is_subclass_of($id, $interfaceTagClassName)) {
                $this->materializeService($id);
            }
        }

        $values = [];
        foreach (($this->tags[$interfaceTagClassName] ?? []) as $containerKey) {
            $values[$containerKey] = $this->getValue($containerKey);
        }

        return $values;
    }

    /**
     * Materializes a deferred service into a container entry and tags it by its interfaces.
     *
     * Where the class supports it, the entry becomes a native lazy proxy
     * ({@see ReflectionClass::newLazyProxy()}): a typed, instanceof-correct instance of the
     * service class whose factory only runs on first actual interaction with the object.
     * Classes that PHP cannot make lazy (internal classes and their non-stdClass subclasses,
     * abstract classes, enums, readonly classes before PHP 8.5) and ids that are not loadable
     * classes fall back to invoking the factory eagerly, as before.
     */
    private function materializeService(string $id): void
    {
        $factory = $this->factories[$id] ?? null;
        if ($factory === null) {
            // Already materialized by a re-entrant call (e.g. triggered through autoloading)
            return;
        }
        // Unset before touching the class: autoloading (class_exists/reflection below) or an
        // eager fallback factory can re-enter the container and must not materialize $id twice
        unset($this->factories[$id]);

        $validator = $this->factoryValidators[$id] ?? null;
        unset($this->factoryValidators[$id]);
        $validator?->__invoke();

        $this->add($id, $this->createLazyService($id, $factory));
    }

    /**
     * Creates the container entry for a deferred service: a native lazy proxy when possible,
     * otherwise the eagerly invoked factory result
     *
     * @param Closure(AspectContainer): object $factory
     */
    private function createLazyService(string $id, Closure $factory): object
    {
        if (!class_exists($id)) {
            return $factory($this);
        }
        $reflection = new ReflectionClass($id);
        if (!self::isLazyProxyCompatible($reflection)) {
            return $factory($this);
        }

        return $reflection->newLazyProxy(function () use ($id, $factory): object {
            $instance = $factory($this);
            if (!$instance instanceof $id) {
                throw new AspectException("Service $id is not properly registered");
            }

            return $instance;
        });
    }

    /**
     * Whether PHP can create a native lazy proxy for the given class
     *
     * @param ReflectionClass<covariant object> $reflection
     */
    private static function isLazyProxyCompatible(ReflectionClass $reflection): bool
    {
        if ($reflection->isInternal() || $reflection->isAbstract() || $reflection->isEnum()) {
            return false;
        }
        // Lazy objects for readonly classes are only supported since PHP 8.5
        if (PHP_VERSION_ID < 80500 && $reflection->isReadOnly()) {
            return false;
        }
        // PHP creates lazy objects of classes without instance properties as already
        // initialized, so the initializer (and with it the service factory) would never
        // run - such services keep the eager construction path
        $hasInstanceProperties = false;
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && !$property->isVirtual()) {
                $hasInstanceProperties = true;
                break;
            }
        }
        if (!$hasInstanceProperties) {
            return false;
        }
        // Subclasses of internal classes (other than stdClass) cannot be lazy
        for ($parent = $reflection->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if ($parent->isInternal()) {
                return $parent->getName() === 'stdClass';
            }
        }

        return true;
    }

    final public function isFreshSince(int $timestamp): bool
    {
        if (!isset($this->cachedMaxTimestamp)) {
            $this->cachedMaxTimestamp = max(array_filter(array_map(filemtime(...), $this->resources)) + [0]);
        }

        return $this->cachedMaxTimestamp <= $timestamp;
    }

    /**
     * Adds a link to the file resource into the container
     *
     * This set of resources is used later to check the freshness of cache
     *
     * @param string $resource Path to the resource
     */
    final protected function addResource(string $resource): void
    {
        if (!isset($this->resources[$resource]) && is_readable($resource)) {
            $this->resources[$resource] = $resource;

            // Invalidation of calculated timestamp
            unset($this->cachedMaxTimestamp);
        }
    }
}
