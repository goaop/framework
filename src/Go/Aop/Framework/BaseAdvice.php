<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Advice;

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
abstract class BaseAdvice implements Advice
{
    /** Constant for undefined order */
    const ORDER_NOT_SET = -1;

    /** @var int Advice order */
    protected $order = self::ORDER_NOT_SET;

    /**
     * Returns the advice order
     * @return int
     */
    public function getAdviceOrder()
    {
        return $this->order;
    }
}
