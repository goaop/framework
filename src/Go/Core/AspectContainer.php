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
use Go\Aop\Framework\ClassFieldAccess;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

/**
 * Aspect container contains list of all pointcuts and advisors
 */
class AspectContainer
{
    /**
     * Prefix for properties interceptor name
     */
    const PROPERTY_PREFIX = "prop";

    /**
     * Prefix for method interceptor name
     */
    const METHOD_PREFIX = "method";

    /**
     * Prefix for static method interceptor name
     */
    const STATIC_METHOD_PREFIX = "static";

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
     * Register an aspect in the container
     *
     * @param Aop\Aspect $aspect Instance of concrete aspect
     */
    public function registerAspect(Aop\Aspect $aspect)
    {
        /** @var $loader AspectLoader */
        $loader = $this->get('aspect.loader');
        $loader->load($aspect);
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

        foreach ($this->advisors as $advisor) {

            if ($advisor instanceof Aop\PointcutAdvisor) {

                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $pointFilter  = $pointcut->getPointFilter();
                    $classAdvices = array_merge_recursive(
                        $classAdvices,
                        $this->getAdvicesFromAdvisor($class, $advisor, $pointFilter)
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
                if ($method->getDeclaringClass()->getName() == $class->getName() && $filter->matches($method)) {
                    $prefix = $method->isStatic() ? self::STATIC_METHOD_PREFIX : self::METHOD_PREFIX;
                    $classAdvices[$prefix . ':'. $method->getName()][] = $advisor->getAdvice();
                }
            }
        }

        // Check properties in class only for PropertyMatcher filters
        if ($filter instanceof Aop\PropertyMatcher) {
            $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED;
            foreach ($class->getProperties($mask) as $property) {
                /** @var $property ReflectionProperty */
                if ($filter->matches($property)) {
                    $classAdvices[self::PROPERTY_PREFIX.':'.$property->getName()][] = $advisor->getAdvice();
                }
            }
        }

        return $classAdvices;
    }

}
