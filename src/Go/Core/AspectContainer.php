<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

use Go\Aop;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutGrammar;
use Go\Instrument\RawAnnotationReader;

use Dissect\Parser\LALR1\Parser;
use Doctrine\Common\Annotations\AnnotationReader;
use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class AspectContainer extends Container
{
    /**
     * Prefix for properties interceptor
     */
    const PROPERTY_PREFIX = "prop";

    /**
     * Prefix for method interceptor
     */
    const METHOD_PREFIX = "method";

    /**
     * Prefix for static method interceptor
     */
    const STATIC_METHOD_PREFIX = "static";

    /**
     * Trait introduction prefix
     */
    const INTRODUCTION_TRAIT_PREFIX = "introduction";

    /**
     * Suffix, that will be added to all proxied class names
     */
    const AOP_PROXIED_SUFFIX = '__AopProxied';


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
     * Flag, that determines if pointcuts are loaded from aspects
     *
     * @var bool
     */
    private $isAdvisorsLoaded = false;

    /**
     * Constructor for container
     */
    public function __construct()
    {
        // Register all services in the container
        $this->set('aspect.loader', function ($container) {
            $aspectLoader = new AspectLoader($container);

            // Register general aspect loader extension
            $aspectLoader->registerLoaderExtension(new GeneralAspectLoaderExtension());
            $aspectLoader->registerLoaderExtension(new IntroductionAspectExtension());

            return $aspectLoader;
        });

        // TODO: use cached annotation reader
        $this->set('aspect.annotation.reader', function () {
            return new AnnotationReader();
        });
        $this->set('aspect.annotation.raw.reader', function () {
            return new RawAnnotationReader();
        });

        // Pointcut services
        $this->set('aspect.pointcut.lexer', function () {
            return new PointcutLexer();
        });
        $this->set('aspect.pointcut.parser', function () {
            return new Parser(
                new PointcutGrammar(),
                // Include production parse table for parser
                include __DIR__ . '/../Aop/Pointcut/PointcutParseTable.php'
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
     * Add an resource for container
     *
     * TODO: use symfony/config component for creating the cache
     *
     * Resources is used to check the freshness of cache
     */
    public function addResource($resource)
    {
        $this->resources[]  = $resource;
        $this->maxTimestamp = 0;
    }

    /**
     * Checks the freshness of container
     *
     * @param integer $timestamp
     *
     * @return bool Whether or not container is fresh
     */
    public function isFresh($timestamp)
    {
        if (!$this->maxTimestamp && $this->resources) {
            $this->maxTimestamp = max(array_map('filemtime', $this->resources));
        }
        return $this->maxTimestamp < $timestamp;
    }

    /**
     * Return list of advices for class
     *
     * @param string|ReflectionClass|ParsedReflectionClass $class Class to advise
     *
     * @return array|Aop\Advice[] List of advices for class
     */
    public function getAdvicesForClass($class)
    {
        if (!$this->isAdvisorsLoaded) {
            $this->loadAdvisorsAndPointcuts();
        }

        $classAdvices = array();
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            $class = new ReflectionClass($class);
        }

        $parentClass = $class->getParentClass();

        if ($parentClass && preg_match('/' . self::AOP_PROXIED_SUFFIX . '$/', $parentClass->name)) {
            $originalClass = $parentClass;
        } else {
            $originalClass = $class;
        }

        foreach ($this->getByTag('advisor') as $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $pointFilter  = $pointcut->getPointFilter();
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getAdvicesFromAdvisor($originalClass, $advisor, $pointFilter)
                    );
                }
            }

            if ($advisor instanceof Aop\IntroductionAdvisor) {
                if ($advisor->getClassFilter()->matches($class)) {
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getIntroductionFromAdvisor($originalClass, $advisor)
                    );
                }
            }
        }
        return $classAdvices;
    }

    /**
     * Returns list of advices from advisor and point filter
     *
     * @param ReflectionClass|ParsedReflectionClass $class Class to inject advices
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param Aop\PointFilter $filter Filter for points
     *
     * @return array
     */
    private function getAdvicesFromAdvisor($class, Aop\PointcutAdvisor $advisor, Aop\PointFilter $filter)
    {
        $classAdvices = array();

        // Check methods in class only for method filters
        if ($filter->getKind() & Aop\PointFilter::KIND_METHOD) {

            $mask = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
            foreach ($class->getMethods($mask) as $method) {
                /** @var $method ReflectionMethod| */
                if ($method->getDeclaringClass()->name == $class->name && $filter->matches($method)) {
                    $prefix = $method->isStatic() ? self::STATIC_METHOD_PREFIX : self::METHOD_PREFIX;
                    $classAdvices[$prefix . ':'. $method->name][] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for property filters
        if ($filter->getKind() & Aop\PointFilter::KIND_PROPERTY) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            foreach ($class->getProperties($mask) as $property) {
                /** @var $property ReflectionProperty */
                if ($filter->matches($property)) {
                    $classAdvices[self::PROPERTY_PREFIX.':'.$property->name][] = $advisor->getAdvice();
                }
            }
        }

        return $classAdvices;
    }

    /**
     * Returns list of introduction advices from advisor
     *
     * @param ReflectionClass|ParsedReflectionClass $class Class to inject advices
     * @param Aop\IntroductionAdvisor $advisor Advisor for class
     *
     * @return array
     */
    private function getIntroductionFromAdvisor($class, $advisor)
    {
        // Do not make introduction for traits
        if ($class->isTrait()) {
            return array();
        }

        /** @var $advice Aop\IntroductionInfo */
        $advice = $advisor->getAdvice();

        return array(
            self::INTRODUCTION_TRAIT_PREFIX.':'.join(':', $advice->getInterfaces()) => $advice
        );
    }

    /**
     * Load pointcuts into container
     *
     * There is no need to always load pointcuts, so we delay loading
     */
    private function loadAdvisorsAndPointcuts()
    {
        /** @var $loader AspectLoader */
        $loader = $this->get('aspect.loader');
        foreach ($this->getByTag('aspect') as $aspect) {
            $loader->load($aspect);
        }
    }
}
