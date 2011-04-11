<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * AdviceContainer class
 *
 * This class describe an action taken by the AOP framework at a particular joinpoint. Different types of advice
 * include "around", "before" and "after" advices.
 *
 *  Around advice is an advice that surrounds a joinpoint such as a method invocation. This is the most powerful kind
 * of advice. Around advices will perform custom behavior before and after the method invocation. They are responsible
 * for choosing whether to proceed to the joinpoint or to shortcut executing by returning their own return value or
 * throwing an exception.
 *  After and before advices are simple closures that will be invoked after and before main invocation.
 * Framework model an advice as an PHP-closure interceptor, maintaining a chain of interceptors "around" the joinpoint:
 *   function($params, Joinpoint $joinPoint) {
 *      echo 'Before action';
 *      // call chain here with Joinpoint->proceed() method
 *      $result = $joinPoint->proceed($params, $joinPoint);
 *      echo 'After action';
 *      return $result;
 *   }
 *
 * @package go
 * @subpackage aop
 */
class AdviceContainer {

    /** Before advice */
    const BEFORE = 'before';

    /** Around advice */
    const AROUND = 'around';

    /** After advice */
    const AFTER = 'after';

    /** @var \Closure|string Callback for advice*/
    protected $advice = null;

    /**
     * Constructs an advice object, which will be applied to joinpoint
     *
     * @param \Closure|string $advice Code of advice or global function name
     * @throws \InvalidArgumentException If $advice param is not callable
     */
    public function __construct($advice)
    {
        if (!is_callable($advice)) {
            throw new \InvalidArgumentException("Advice should be callable.");
        }
        $this->advice = $advice;
    }

    /**
     * AdviceContainer invoker
     *
     * @param array $params Associative array of params
     * @param Joinpoint|null $joinpoint
     * @return mixed
     */
    public function __invoke(array $params = array(), Joinpoint $joinpoint = null)
    {
        $advice = $this->advice;
        return $advice($params, $joinpoint, $this);
    }
}
