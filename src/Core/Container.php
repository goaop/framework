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
    private array $resources = [];

    /**
     * Constructor for container
     *
     * @param array<string> $resources [Optional] List of additional resources to track for container invalidation
     */
    public function __construct(array $resources = [])
    {
        $this->resources = array_combine($resources, $resources);

        $this->addLazy(PointcutLexer::class, fn() => new PointcutLexer());

        $this->addLazy(PointcutParser::class, fn(AspectContainer $container) => new PointcutParser(
            new PointcutGrammar($container)
        ));

        $this->addLazy(AdviceMatcher::class, fn(AspectContainer $container) => new AdviceMatcher(
            (bool) $container->getValue('kernel.interceptFunctions')
        ));

        $this->addLazy(AspectLoader::class, function (AspectContainer $container) {
            $lexer  = $container->getService(PointcutLexer::class);
            $parser = $container->getService(PointcutParser::class);

            return new AspectLoader(
                $container,
                new AttributeAspectLoaderExtension($lexer, $parser),
                new IntroductionAspectExtension($lexer, $parser)
            );
        });

        $this->addLazy(CachedAspectLoader::class, function (AspectContainer $container) {
            $options = $container->getValue('kernel.options');
            if (is_array($options) && !empty($options['cacheDir'])) {
                $loader = new CachedAspectLoader($container, AspectLoader::class, $options);
            } else {
                $loader = $container->getService(AspectLoader::class);
            }

            return $loader;
        });

        $this->addLazy(LazyAdvisorAccessor::class, fn(AspectContainer $container) => new LazyAdvisorAccessor(
            $container,
            $container->getService(CachedAspectLoader::class)
        ));

        $this->addLazy(CachePathManager::class, fn(AspectContainer $container) => new CachePathManager(
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
        if (is_object($value) && !$value instanceof Closure) {
            $reflectionObject = new ReflectionObject($value);
            foreach ($reflectionObject->getInterfaceNames() as $interfaceTagName) {
                $this->tags[$interfaceTagName][] = $id;
            }
            // Also register corresponding file names to track freshness of container
            $fileName = $reflectionObject->getFileName();
            if (is_string($fileName)) {
                $this->addResource($fileName);
            }
        }
    }

    final public function getService(string $className): object
    {
        if (!isset($this->values[$className])) {
            throw new OutOfBoundsException("Value {$className} is not defined in the container");
        }
        // Support for lazy-evaluation and initialization
        if ($this->values[$className] instanceof Closure) {
            return $this->values[$className]($this);
        }
        if (!$this->values[$className] instanceof $className) {
            throw new AspectException("Service {$className} is not properly registered");
        }

        return $this->values[$className];
    }

    final public function getValue(string $key): mixed
    {
        if (!isset($this->values[$key])) {
            throw new OutOfBoundsException("Value {$key} is not defined in the container");
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
        $this->add($id, function (self $container) use ($id, $lazyDefinitionClosure): object {

            $evaluatedLazyValue = $lazyDefinitionClosure($container);
            // Here we just replace Closure with resolved value to optimize access
            $container->values[$id] = $evaluatedLazyValue;

            return $evaluatedLazyValue;
        });
    }
}
