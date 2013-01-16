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

use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class AspectContainer
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
     * List of named pointcuts in the container
     *
     * @var array|Aop\Pointcut[]
     */
    protected $pointcuts = array();

    /**
     * List of named and indexed advisors in the container
     *
     * @var array|Aop\Advisor[]
     */
    protected $advisors = array();

    /**
     * List of services in the container
     *
     * @var array|object[]
     */
    protected $services = array();

    /**
     * List of registered aspects
     *
     * @var array
     */
    protected $aspects = array();

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
     * Set a service into the container
     *
     * @param string $id Key for service
     * @param object $service Service to store
     */
    public function set($id, $service)
    {
        $this->services[$id] = $service;
    }

    /**
     * Return a service from the container
     *
     * @param string $id Service key
     *
     * @return object
     * @throws \OutOfBoundsException if service was not found
     */
    public function get($id)
    {
        if (is_numeric($id) || !isset($this->services[$id])) {
            throw new \OutOfBoundsException("Unknown service {$id}");
        }
        return $this->services[$id];
    }

    /**
     * Returns a pointcut by identifier
     *
     * @param string $id Pointcut identifier
     *
     * @return Aop\Pointcut
     *
     * @throws \OutOfBoundsException if pointcut key is invalid
     */
    public function getPointcut($id)
    {
        if (is_numeric($id) || !isset($this->pointcuts[$id])) {
            throw new \OutOfBoundsException("Unknown pointcut {$id}");
        }
        return $this->pointcuts[$id];
    }

    /**
     * Store the pointcut in the container
     *
     * @param Aop\Pointcut $pointcut Instance
     * @param string $id Key for pointcut
     */
    public function registerPointcut(Aop\Pointcut $pointcut, $id = null)
    {
        if ($id) {
            $this->pointcuts[$id] = $pointcut;
        } else {
            $this->pointcuts[] = $pointcut;
        }
    }

    /**
     * Returns an advisor by identifier
     *
     * @param string $id Advisor identifier
     *
     * @return Aop\Advisor
     *
     * @throws \OutOfBoundsException if advisor key is invalid
     */
    public function getAdvisor($id)
    {
        if (is_numeric($id) || !isset($this->advisors[$id])) {
            throw new \OutOfBoundsException("Unknown advisor {$id}");
        }
        return $this->advisors[$id];
    }

    /**
     * Store the advisor in the container
     *
     * @param Aop\Advisor $advisor Instance
     * @param string $id Key for advisor
     */
    public function registerAdvisor(Aop\Advisor $advisor, $id = null)
    {
        if ($id) {
            $this->advisors[$id] = $advisor;
        } else {
            $this->advisors[] = $advisor;
        }
    }

    /**
     * Returns an aspect by id or class name
     *
     * @param string $aspectName Aspect name
     *
     * @throws \OutOfBoundsException If aspect is unknown
     *
     * @return Aop\Aspect
     */
    public function getAspect($aspectName)
    {
        if (!isset($this->aspects[$aspectName])) {
            throw new \OutOfBoundsException("Unknown aspect {$aspectName}");
        }
        return $this->aspects[$aspectName];
    }

    /**
     * Register an aspect in the container
     *
     * @param Aop\Aspect $aspect Instance of concrete aspect
     * @param string $id Key for aspect
     *
     * @throws \LogicException if aspect was already registered
     */
    public function registerAspect(Aop\Aspect $aspect)
    {
        $aspectName = get_class($aspect);
        if (!empty($this->aspects[$aspectName])) {
            throw new \LogicException("Only one instance of single aspect can be registered at once");
        }

        /** @var $loader AspectLoader */
        $loader = $this->get('aspect.loader');
        $loader->load($aspect);

        $this->aspects[$aspectName] = $aspect;
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

        foreach ($this->advisors as $advisor) {

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
     * @param ReflectionClass|ParsedReflectionClass|string $class Class to inject advices
     * @param Aop\PointcutAdvisor $advisor Advisor for class
     * @param Aop\PointFilter $filter Filter for points
     *
     * @return array
     */
    private function getAdvicesFromAdvisor($class, Aop\PointcutAdvisor $advisor, Aop\PointFilter $filter)
    {
        $classAdvices = array();

        // Check methods in class only for MethodMatcher filters
        if ($filter instanceof Aop\MethodMatcher) {

            $mask = ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
            foreach ($class->getMethods($mask) as $method) {
                /** @var $method ReflectionMethod| */
                if ($method->getDeclaringClass()->name == $class->name && $filter->matches($method)) {
                    $prefix = $method->isStatic() ? self::STATIC_METHOD_PREFIX : self::METHOD_PREFIX;
                    $classAdvices[$prefix . ':'. $method->name][] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for PropertyMatcher filters
        if ($filter instanceof Aop\PropertyMatcher) {
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
     * @param ReflectionClass|ParsedReflectionClass|string $class Class to inject advices
     * @param Aop\IntroductionAdvisor $advisor Advisor for class
     *
     * @return array
     */
    private function getIntroductionFromAdvisor($class, $advisor)
    {
        /** @var $advice Aop\IntroductionInfo */
        $advice = $advisor->getAdvice();

        return array(
            self::INTRODUCTION_TRAIT_PREFIX.':'.join(':', $advice->getInterfaces()) => $advice
        );
    }
}
