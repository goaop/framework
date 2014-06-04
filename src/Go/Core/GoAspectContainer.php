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

use Doctrine\Common\Annotations\FileCacheReader;
use ReflectionClass;
use Go\Aop;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Aop\Pointcut\PointcutParser;
use Go\Instrument\RawAnnotationReader;
use Doctrine\Common\Annotations\AnnotationReader;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class GoAspectContainer extends Container implements AspectContainer
{
    /**
     * List of resources for application
     *
     * @var array
     */
    protected $resources = array();

    /**
     * Cached timestamp for resources
     *
     * @var integer
     */
    protected $maxTimestamp = 0;

    /**
     * Constructor for container
     */
    public function __construct()
    {
        // Register all services in the container
        $this->share('aspect.loader', function ($container) {
            $aspectLoader = new AspectLoader(
                $container,
                $container->get('aspect.annotation.reader')
            );
            $lexer  = $container->get('aspect.pointcut.lexer');
            $parser = $container->get('aspect.pointcut.parser');

            // Register general aspect loader extension
            $aspectLoader->registerLoaderExtension(new GeneralAspectLoaderExtension($lexer, $parser));
            $aspectLoader->registerLoaderExtension(new IntroductionAspectExtension($lexer, $parser));

            return $aspectLoader;
        });

        $this->share('aspect.cached.loader', function ($container) {
            $cachedAspectLoader = new CachedAspectLoader(
                $container,
                'aspect.loader',
                $container->get('kernel.options')
            );

            return $cachedAspectLoader;
        });

        $this->share('aspect.advisor.accessor', function ($container) {
            return new LazyAdvisorAccessor(
                $container,
                $container->get('aspect.cached.loader')
            );
        });

        $this->share('aspect.advice_matcher', function ($container) {
            return new AdviceMatcher(
                $container->get('aspect.loader'),
                $container,
                $container->get('kernel.interceptFunctions')
            );
        });

        $this->share('aspect.annotation.reader', function ($container) {
            $options = $container->get('kernel.options');
            $reader  = new AnnotationReader();
            if (!empty($options['cacheDir'])) {
                $reader  = new FileCacheReader(
                    $reader,
                    $options['cacheDir'] . DIRECTORY_SEPARATOR . '_annotations' . DIRECTORY_SEPARATOR,
                    $options['debug']
                );
            }

            return $reader;
        });
        $this->share('aspect.annotation.raw.reader', function () {
            return new RawAnnotationReader();
        });

        // Pointcut services
        $this->share('aspect.pointcut.lexer', function () {
            return new PointcutLexer();
        });
        $this->share('aspect.pointcut.parser', function ($container) {
            return new PointcutParser(
                new PointcutGrammar(
                    $container,
                    $container->get('aspect.annotation.raw.reader')
                )
            );
        });
    }

    /**
     * Returns a pointcut by identifier
     *
     * @param string $id Pointcut identifier
     *
     * @return Aop\Pointcut
     */
    public function getPointcut($id)
    {
        return $this->get("pointcut.{$id}");
    }

    /**
     * Store the pointcut in the container
     *
     * @param Aop\Pointcut $pointcut Instance
     * @param string $id Key for pointcut
     */
    public function registerPointcut(Aop\Pointcut $pointcut, $id)
    {
        $this->set("pointcut.{$id}", $pointcut, array('pointcut'));
    }

    /**
     * Store the advisor in the container
     *
     * @param Aop\Advisor $advisor Instance
     * @param string $id Key for advisor
     */
    public function registerAdvisor(Aop\Advisor $advisor, $id)
    {
        $this->set("advisor.{$id}", $advisor, array('advisor'));
    }

    /**
     * Returns an aspect by id or class name
     *
     * @param string $aspectName Aspect name
     *
     * @return Aop\Aspect
     */
    public function getAspect($aspectName)
    {
        return $this->get("aspect.{$aspectName}");
    }

    /**
     * Register an aspect in the container
     *
     * @param Aop\Aspect $aspect Instance of concrete aspect
     */
    public function registerAspect(Aop\Aspect $aspect)
    {
        $refAspect = new ReflectionClass($aspect);
        $this->set("aspect.{$refAspect->name}", $aspect, array('aspect'));
        $this->addResource($refAspect->getFileName());
    }

    /**
     * Add an AOP resource to the container
     *
     * TODO: use symfony/config component for creating the cache
     *
     * Resources is used to check the freshness of AOP cache
     */
    public function addResource($resource)
    {
        $this->resources[]  = $resource;
        $this->maxTimestamp = 0;
    }

    /**
     * Returns list of AOP resources
     *
     * @return array
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Checks the freshness of AOP cache
     *
     * @param integer $timestamp
     *
     * @return bool Whether or not concrete file is fresh
     */
    public function isFresh($timestamp)
    {
        if (!$this->maxTimestamp && $this->resources) {
            $this->maxTimestamp = max(array_map('filemtime', $this->resources));
        }

        return $this->maxTimestamp <= $timestamp;
    }
}
