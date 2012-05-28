<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use SplObjectStorage;

use Go\Aop\Advisor;
use Go\Aop\ClassFilter;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;
use Go\Aop\PointFilter;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\ReflectionMethodInvocation;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

/**
 * Advisor registry contains list of all pointcuts
 */
class AdvisorRegistry
{
    /**
     * Prefix for properties interceptor name
     */
    const PROPERTY_PREFIX = "prop:";

    /**
     * Prefix for method interceptor name
     */
    const METHOD_PREFIX = "method:";

    /**
     * List of advisors (aspects)
     *
     * @var array|Advisor[]
     */
    protected static $advisors = array();

    /**
     * Register an advisor in registry
     *
     * @param Advisor $advisor Instance of advisor with advice
     */
    public static function register(Advisor $advisor)
    {
        self::$advisors[] = $advisor;
    }

    /**
     * Make an advise for a class and return list of joinpoints with correct advices at that points
     *
     * @param string|ReflectionClass|ParsedReflectionClass $class Class to advise
     *
     * @return array|Joinpoint[] List of joinpoints for class
     */
    public static function advise($class)
    {
        $classAdvices = array();
        if (!$class instanceof ReflectionClass && !$class instanceof ParsedReflectionClass) {
            $class = new ReflectionClass($class);
        }
        foreach (self::$advisors as $advisor) {
            if ($advisor instanceof PointcutAdvisor) {
                /** @var $advisor PointcutAdvisor */
                /** @var $pointcut Pointcut */
                $pointcut = $advisor->getPointcut();
                if ($pointcut->getClassFilter()->matches($class)) {
                    $pointFilter = $pointcut->getPointFilter();
                    $classAdvices = array_merge($classAdvices, self::getClassAdvices($class, $advisor, $pointFilter));
                }
            }
        }
        return $classAdvices ? self::wrapWithJoinpoints($classAdvices, $class) : array();
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[] $classAdvices Advices for specific class
     * @param ReflectionClass|ParsedReflectionClass $class Instance of reflection of class
     *
     * @return array|Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinpoints($classAdvices, $class)
    {
        $className  = $class->getName();
        $joinpoints = array();
        foreach ($classAdvices as $name => $advices) {

            // Fields use prop:$name format, so use this information
            if (strpos($name, self::PROPERTY_PREFIX) === 0) {
                $propertyName      = substr($name, strlen(self::PROPERTY_PREFIX));
                $joinpoints[$name] = new ClassFieldAccess($className, $propertyName, $advices);
            } elseif (strpos($name, self::METHOD_PREFIX) === 0) {
                $methodName        = substr($name, strlen(self::METHOD_PREFIX));
                $joinpoints[$name] = new ReflectionMethodInvocation($className, $methodName, $advices);
            }
        }
        return $joinpoints;
    }

    /**
     * Inject advices into given class
     *
     * @param ReflectionClass|ParsedReflectionClass|string $class Class to inject advices
     *
     * @return void
     */
    public static function injectAdvices($proxyClass, $target)
    {
        if (!$proxyClass instanceof ReflectionClass && !$proxyClass instanceof ParsedReflectionClass) {
            $proxyClass = new ReflectionClass($proxyClass);
        }
        /** @var $prop ReflectionProperty|ParsedReflectionProperty */
        $prop = $proxyClass->getProperty('__joinPoints');
        $prop->setAccessible(true);
        $prop->setValue(self::advise($target));
    }

    /**
     * Returns list of advices for joinpoints
     *
     * @param ReflectionClass|ParsedReflectionClass|string $class Class to inject advices
     * @param PointcutAdvisor $advisor Advisor for class
     * @param PointFilter $filter Filter for points
     *
     * @return array
     */
    private static function getClassAdvices($class, PointcutAdvisor $advisor, PointFilter $filter)
    {
        $classAdvices = array();
        foreach ($class->getMethods() as $method) {
            /** @var $method ReflectionMethod| */
            if ($filter->matches($method)) {
                $classAdvices[self::METHOD_PREFIX . $method->getName()][] = $advisor->getAdvice();
            }
        }

        foreach ($class->getProperties() as $property) {
            /** @var $property ReflectionProperty */
            if ($filter->matches($property)) {
                $classAdvices[self::PROPERTY_PREFIX . $property->getName()][] = $advisor->getAdvice();
            }
        }
        return $classAdvices;
    }
}
