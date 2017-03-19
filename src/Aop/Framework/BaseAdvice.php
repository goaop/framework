<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use ReflectionFunction;
use ReflectionMethod;
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
 *   function (Joinpoint $joinPoint) {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed();
 *      echo 'After action';
 *      return $result;
 *   }
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
     * @var array|Closure[]
     */
    protected static $localAdvicesCache = [];

    /**
     * Returns the advice order
     */
    public function getAdviceOrder() : int
    {
        return $this->order;
    }

    /**
     * Serialize advice method into array
     *
     * @param Closure $adviceMethod An advice for aspect
     *
     * @return array
     */
    public static function serializeAdvice(Closure $adviceMethod)
    {
        $refAdvice = new ReflectionFunction($adviceMethod);

        return [
            'method' => $refAdvice->name,
            'aspect' => get_class($refAdvice->getClosureThis())
        ];
    }

    /**
     * Unserialize an advice
     *
     * @param array $adviceData Information about advice
     *
     * @return Closure
     */
    public static function unserializeAdvice(array $adviceData)
    {
        $aspectName = $adviceData['aspect'];
        $methodName = $adviceData['method'];

        if (!isset(static::$localAdvicesCache["$aspectName->$methodName"])) {
            $refMethod = new ReflectionMethod($aspectName, $methodName);
            $aspect    = AspectKernel::getInstance()->getContainer()->getAspect($aspectName);
            $advice    = $refMethod->getClosure($aspect);
            static::$localAdvicesCache["$aspectName->$methodName"] = $advice;
        }

        return static::$localAdvicesCache["$aspectName->$methodName"];
    }
}
