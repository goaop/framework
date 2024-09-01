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

use Closure;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Instrument\ClassLoading\CachePathManager;
use OutOfBoundsException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionObject;

/**
 * DI-container
 */
class Container implements AspectContainer
{
    /**
     * @var (array&array<string,mixed>) Hashmap of items/services in the container
     */
    private array $values = [];

    /**
     * @var (array&array<class-string, list<string>>) Holds information about mapping of interface tags into identifiers
     */
    private array $tags = [];

    /**
     * Cached timestamp for resources, might be uninitialized if {@see self::hasAnyResourceChangedSince()} is not called yet
     */
    private int $cachedMaxTimestamp;

    /**
     * @var (array&array<string, string>) Hashmap of resources for application
     */
    private array $resources;

    /**
     * Constructor for container
     *
     * @param array<string> $resources [Optional] List of additional resources to track for container invalidation
     */
    public function __construct(array $resources = [])
    {
        $this->resources = array_combine($resources, $resources);

        $this->addLazy(PointcutLexer::class, fn(): PointcutLexer => new PointcutLexer());

        $this->addLazy(PointcutParser::class, fn(AspectContainer $container): PointcutParser => new PointcutParser(
            new PointcutGrammar($container)
        ));

        $this->addLazy(AdviceMatcher::class, fn(AspectContainer $container): AdviceMatcher => new AdviceMatcher(
            (bool) $container->getValue('kernel.interceptFunctions')
        ));

        $this->addLazy(AttributeAspectLoaderExtension::class, fn(AspectContainer $container): AttributeAspectLoaderExtension => new AttributeAspectLoaderExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class)
        ));

        $this->addLazy(IntroductionAspectExtension::class, fn(AspectContainer $container): IntroductionAspectExtension => new IntroductionAspectExtension(
            $container->getService(PointcutLexer::class),
            $container->getService(PointcutParser::class)
        ));

        $this->addLazy(AspectLoader::class, fn(AspectContainer $container): AspectLoader => new AspectLoader(
            $container,
            $container->getService(AttributeAspectLoaderExtension::class),
            $container->getService(IntroductionAspectExtension::class),
        ));

        $this->addLazy(CachedAspectLoader::class, function (AspectContainer $container) {
            $options = $container->getValue('kernel.options');
            if (is_array($options) && !empty($options['cacheDir'])) {
                $loader = new CachedAspectLoader($container, AspectLoader::class, $options);
            } else {
                $loader = $container->getService(AspectLoader::class);
            }

            return $loader;
        });

        $this->addLazy(LazyAdvisorAccessor::class, fn(AspectContainer $container): LazyAdvisorAccessor => new LazyAdvisorAccessor(
            $container,
            $container->getService(CachedAspectLoader::class)
        ));

        $this->addLazy(CachePathManager::class, fn(AspectContainer $container): CachePathManager => new CachePathManager(
            $container->getService(AspectKernel::class)
        ));
    }

    final public function registerAspect(Aspect $aspect): void
    {
        $this->add($aspect::class, $aspect);
    }

    final public function add(string $id, mixed $value): void
    {
        $this->values[$id] = $value;

        // For objects we would like to use interface names as tags, eg Pointcut, Advisor, Aspect, etc
        if (is_object($value)) {
            // If it is real object (not a lazy closure), then we use it directly
            if (!$value instanceof Closure) {
                $reflectionInstance = new ReflectionObject($value);
            } else {
                // If it is our lazy Closure, we look at internal closure return type to check if it is a class
                $reflectionClosure     = new ReflectionFunction($value);
                $lazyDefinitionClosure = $reflectionClosure->getStaticVariables()['lazyDefinitionClosure'] ?? null;
                $lazyReturnType        = $lazyDefinitionClosure
                    ? (new ReflectionFunction($lazyDefinitionClosure))->getReturnType()
                    : null;

                if ($lazyReturnType instanceof ReflectionNamedType && class_exists($lazyReturnType->getName())) {
                    $reflectionInstance = new ReflectionClass($lazyReturnType->getName());
                }
            }
        }

        if (isset($reflectionInstance)) {
            foreach ($reflectionInstance->getInterfaceNames() as $interfaceTagName) {
                $this->tags[$interfaceTagName][] = $id;
            }
            // Also register corresponding file names to track freshness of container
            $fileName = $reflectionInstance->getFileName();
            if (is_string($fileName)) {
                $this->addResource($fileName);
            }
        }
    }

    final public function getService(string $className): object
    {
        if (!isset($this->values[$className])) {
            throw new OutOfBoundsException("Value $className is not defined in the container");
        }
        // Support for lazy-evaluation and initialization
        if ($this->values[$className] instanceof Closure) {
            $this->values[$className]($this);
        }
        if (!$this->values[$className] instanceof $className) {
            throw new AspectException("Service $className is not properly registered");
        }

        return $this->values[$className];
    }

    final public function getValue(string $key): mixed
    {
        if (!isset($this->values[$key])) {
            throw new OutOfBoundsException("Value $key is not defined in the container");
        }

        return $this->values[$key];
    }

    final public function has(string $id): bool
    {
        return isset($this->values[$id]);
    }

    final public function getServicesByInterface(string $interfaceTagClassName): array
    {
        $values = [];
        foreach (($this->tags[$interfaceTagClassName] ?? []) as $containerKey) {
            $values[$containerKey] = $this->getValue($containerKey);
        }

        return $values;
    }

    final public function hasAnyResourceChangedSince(int $timestamp): bool
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
        if (!isset($this->resources[$resource])) {
            $this->resources[$resource] = $resource;

            // Invalidation of calculated timestamp
            unset($this->cachedMaxTimestamp);
        }
    }

    /**
     * Add value in the container, uses lazy-loading scheme to optimize init time
     *
     * @param Closure(AspectContainer $container): object $lazyDefinitionClosure
     */
    final protected function addLazy(string $id, Closure $lazyDefinitionClosure): void
    {
        $this->add($id, function (self $container) use ($id, $lazyDefinitionClosure): void {

            $evaluatedLazyValue = $lazyDefinitionClosure($container);
            // Here we just replace Closure with resolved value to optimize access
            $container->values[$id] = $evaluatedLazyValue;
        });
    }
}
