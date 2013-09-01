<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use ReflectionFunction;
use ReflectionMethod;

use Go\Aop\Aspect;
use Go\Aop\Intercept\Joinpoint;
use Go\Core\AspectKernel;

/**
 * Base class for all framework advices implementations
 *
 *  This class describe an action taken by the AOP framework at a particular
 * joinpoint. Different types of advice include "around", "before" and "after"
 * advices.
 *
 *  Around advice is an advice that surrounds a joinpoint such as a method
 * invocation. This is the most powerful kind of advice. Around advices will
 * perform custom behavior before and after the method invocation. They are
 * responsible for choosing whether to proceed to the joinpoint or to shortcut
 * executing by returning their own return value or throwing an exception.
 *  After and before advices are simple closures that will be invoked after and
 * before main invocation.
 *  Framework model an advice as an PHP-closure interceptor, maintaining a
 * chain of interceptors "around" the joinpoint:
 *   function(Joinpoint $joinPoint) {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed();
 *      echo 'After action';
 *      return $result;
 *   }
 * @author Lissachenko Alexander
 */
abstract class BaseAdvice implements OrderedAdvice
{
    /**
     * Advice order
     *
     * @var int
     */
    protected $order = 0;

    /**
     * Local cache of advices for faster unserialization on big projects
     *
     * @var array
     */
    protected static $localAdvicesCache = array();

    /**
     * Returns the advice order
     *
     * @return int
     */
    public function getAdviceOrder()
    {
        return $this->order;
    }

    /**
     * Serialize advice method into array
     *
     * @param callable|\Closure $adviceMethod An advice for aspect
     *
     * @return array
     */
    public static function serializeAdvice($adviceMethod)
    {
        $refAdvice    = new ReflectionFunction($adviceMethod);
        $refVariables = $refAdvice->getStaticVariables();
        $scope        = 'aspect';
        if (isset($refVariables['scope'])) {
            $scope     = $refVariables['scope'];
            $refAdvice = new ReflectionFunction($refVariables['adviceCallback']);
        }
        if (IS_MODERN_PHP) {
            $method = $refAdvice;
            $aspect = $refAdvice->getClosureThis();
        } else {
            $vars   = $refAdvice->getStaticVariables();
            $method = $vars['refMethod'];
            $aspect = $vars['aspect'];
        }
        return array(
            'scope'  => $scope,
            'method' => $method->name,
            'aspect' => get_class($aspect)
        );
    }

    /**
     * Unserialize an advice
     *
     * @param array $adviceData Information about advice
     *
     * @return callable|\Closure
     */
    public static function unserializeAdvice($adviceData)
    {
        $aspectName = $adviceData['aspect'];
        $methodName = $adviceData['method'];
        $scope      = $adviceData['scope'];

        if (!isset(static::$localAdvicesCache["$aspectName->$methodName"]['aspect'])) {
            $refMethod = new ReflectionMethod($aspectName, $methodName);
            $aspect    = AspectKernel::getInstance()->getContainer()->getAspect($aspectName);
            $advice    = static::fromAspectReflection($aspect, $refMethod);
            static::$localAdvicesCache["$aspectName->$methodName"]['aspect'] = $advice;
        }

        if ($scope !== 'aspect' && !isset(static::$localAdvicesCache["$aspectName->$methodName"][$scope])) {
            $advice = static::$localAdvicesCache["$aspectName->$methodName"]['aspect'];
            $advice = static::createScopeCallback($advice, $scope);
            static::$localAdvicesCache["$aspectName->$methodName"][$scope] = $advice;
        }

        return static::$localAdvicesCache["$aspectName->$methodName"][$scope];
    }

    /**
     * Returns an advice from aspect method reflection
     *
     * @param Aspect $aspect Instance of aspect
     * @param ReflectionMethod $refMethod Reflection method of aspect
     *
     * @return callable|object
     */
    public static function fromAspectReflection(Aspect $aspect, ReflectionMethod $refMethod)
    {
        if (IS_MODERN_PHP) {
            return $refMethod->getClosure($aspect);
        } else {
            return function () use ($aspect, $refMethod) {
                return $refMethod->invokeArgs($aspect, func_get_args());
            };
        }
    }

    /**
     * Creates an advice with respect to the desired scope
     *
     * @param callable| $adviceCallback Advice to call
     * @param string $scope Scope for callback
     *
     * @throws \InvalidArgumentException is scope is not supported
     * @return callable
     */
    public static function createScopeCallback($adviceCallback, $scope)
    {
        switch ($scope) {
            case 'aspect':
                return $adviceCallback;

            case 'proxy':
                return function (Joinpoint $joinpoint) use ($adviceCallback, $scope) {
                    $instance    = $joinpoint->getThis();
                    $isNotObject = $instance !== (object) $instance;
                    $object      = $isNotObject ? null : $instance;
                    $target      = $isNotObject ? $instance : get_class($instance);
                    $callback    = $adviceCallback->bindTo($object, $target);

                    return $callback($joinpoint);
                };

            case 'target':
                return function (Joinpoint $joinpoint) use ($adviceCallback, $scope) {
                    $instance    = $joinpoint->getThis();
                    $isNotObject = $instance !== (object) $instance;
                    $object      = $isNotObject ? null : $instance;
                    $target      = $isNotObject ? $instance : get_parent_class($instance);
                    $callback    = $adviceCallback->bindTo($object, $target);

                    return $callback($joinpoint);
                };

            default:
                throw new \InvalidArgumentException("Unsupported scope `{$scope}`");
        }
    }
}
