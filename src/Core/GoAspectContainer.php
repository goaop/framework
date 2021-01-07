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

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache as DoctrineCache;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Instrument\ClassLoading\CachePathManager;
use ReflectionClass;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class GoAspectContainer extends Container
{
    /**
     * List of resources for application
     *
     * @var string[]
     */
    protected array $resources = [];

    /**
     * Cached timestamp for resources
     */
    protected int $maxTimestamp = 0;

    /**
     * Constructor for container
     */
    public function __construct()
    {
        // Register all services in the container
        $this->share('aspect.loader', function (Container $container) {
            $annotReader  = $container->get('aspect.annotation.reader');
            $aspectLoader = new AspectLoader($container);
            $lexer        = $container->get('aspect.pointcut.lexer');
            $parser       = $container->get('aspect.pointcut.parser');

            // Register general aspect loader extension
            $aspectLoader->registerLoaderExtension(new GeneralAspectLoaderExtension($lexer, $parser, $annotReader));
            $aspectLoader->registerLoaderExtension(new IntroductionAspectExtension($lexer, $parser, $annotReader));

            return $aspectLoader;
        });

        $this->share('aspect.cached.loader', function (Container $container) {
            $options = $container->get('kernel.options');
            if (!empty($options['cacheDir'])) {
                $loader = new CachedAspectLoader(
                    $container,
                    'aspect.loader',
                    $container->get('kernel.options')
                );
            } else {
                $loader = $container->get('aspect.loader');
            }

            return $loader;
        });

        $this->share('aspect.advisor.accessor', fn(Container $container) => new LazyAdvisorAccessor(
            $container,
            $container->get('aspect.cached.loader')
        ));

        $this->share('aspect.advice_matcher', fn(Container $container) => new AdviceMatcher(
            $container->get('kernel.interceptFunctions')
        ));

        $this->share('aspect.annotation.cache', function (Container $container) {
            $options = $container->get('kernel.options');

            if (!empty($options['annotationCache'])) {
                return $options['annotationCache'];
            }

            if (!empty($options['cacheDir'])) {
                return new DoctrineCache\FilesystemCache(
                    $options['cacheDir'] . DIRECTORY_SEPARATOR . '_annotations' . DIRECTORY_SEPARATOR,
                    '.annotations.cache',
                    0777 & (~(int)$options['cacheFileMode'])
                );
            }

            return new DoctrineCache\ArrayCache();
        });

        $this->share('aspect.annotation.reader', function (Container $container) {
            $options = $container->get('kernel.options');

            return new CachedReader(
                new AnnotationReader(),
                $container->get('aspect.annotation.cache'),
                $options['debug'] ?? false
            );
        });

        $this->share('aspect.cache.path.manager', fn(Container $container) => new CachePathManager($container->get('kernel')));

        // Pointcut services
        $this->share('aspect.pointcut.lexer', fn() => new PointcutLexer());
        $this->share('aspect.pointcut.parser', fn(Container $container) => new PointcutParser(
            new PointcutGrammar(
                $container,
                $container->get('aspect.annotation.reader')
            )
        ));
    }

    /**
     * Returns a pointcut by identifier
     */
    public function getPointcut(string $id): Pointcut
    {
        return $this->get("pointcut.{$id}");
    }

    /**
     * Store the pointcut in the container
     */
    public function registerPointcut(Pointcut $pointcut, string $id): void
    {
        $this->set("pointcut.{$id}", $pointcut, ['pointcut']);
    }

    /**
     * Returns an advisor by identifier
     */
    public function getAdvisor(string $id): Advisor
    {
        return $this->get("advisor.{$id}");
    }

    /**
     * Store the advisor in the container
     */
    public function registerAdvisor(Advisor $advisor, string $id): void
    {
        $this->set("advisor.{$id}", $advisor, ['advisor']);
    }

    /**
     * Returns an aspect by id or class name
     */
    public function getAspect(string $aspectName): Aspect
    {
        return $this->get("aspect.{$aspectName}");
    }

    /**
     * Register an aspect in the container
     */
    public function registerAspect(Aspect $aspect): void
    {
        $refAspect = new ReflectionClass($aspect);
        $this->set("aspect.{$refAspect->name}", $aspect, ['aspect']);
        $this->addResource($refAspect->getFileName());
    }

    /**
     * Add an AOP resource to the container
     * Resources is used to check the freshness of AOP cache
     *
     * @param string $resource Path to the resource
     */
    public function addResource(string $resource): void
    {
        $this->resources[]  = $resource;
        $this->maxTimestamp = 0;
    }

    /**
     * Returns list of AOP resources
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * Checks the freshness of AOP cache
     *
     * @return bool Whether or not concrete file is fresh
     */
    public function isFresh(int $timestamp): bool
    {
        if (!$this->maxTimestamp && !empty($this->resources)) {
            $this->maxTimestamp = max(array_map('filemtime', $this->resources));
        }

        return $this->maxTimestamp <= $timestamp;
    }
}
