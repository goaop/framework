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
     * List of advisors (aspects)
     *
     * @var array|Advisor[]
     */
    protected static $advisors = array();

    public static function register(Advisor $advisor)
    {
        self::$advisors[] = $advisor;
    }

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
        $joinpoints = array();
        foreach ($classAdvices as $name => $advices) {
            $joinpoints[$name] = new ReflectionMethodInvocation($class->getName(), $name, $advices);
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
                $classAdvices[$method->getName()][] = $advisor->getAdvice();
            }
        }
        return $classAdvices;
    }
}
