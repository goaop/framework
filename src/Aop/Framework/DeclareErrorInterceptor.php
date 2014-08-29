<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInterceptor;
use Go\Aop\Intercept\MethodInvocation;
use Go\Aop\Pointcut;

/**
 * Interceptor to dynamically trigger an user notice/warning/error on method call
 *
 * This interceptor can be used as active replacement for the @deprecated tag or to notify about
 * probable issues with specific method.
 */
class DeclareErrorInterceptor extends BaseInterceptor implements MethodInterceptor
{

    /**
     * Error message to show for this interceptor
     *
     * @var string
     */
    private $message = '';

    /**
     * Default level of error
     *
     * @var int
     */
    private $level = E_USER_NOTICE;

    /**
     * Default constructor for interceptor
     *
     * @param string $message Text message for error
     * @param int $level Level of error
     * @param Pointcut $pointcut Pointcut instance where interceptor should be called
     */
    public function __construct($message, $level, Pointcut $pointcut = null)
    {
        $adviceMethod  = $this->getDeclareErrorAdvice();
        $this->message = $message;
        $this->level   = $level;
        parent::__construct($adviceMethod, -256, $pointcut);
    }

    /**
     * Serializes an interceptor into string representation
     *
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        $vars = array_filter(get_object_vars($this));
        unset($vars['adviceMethod']);

        return serialize($vars);
    }

    /**
     * Unserialize an interceptor from the string
     *
     * @param string $serialized The string representation of the object.
     * @return void
     */
    public function unserialize($serialized)
    {
        $vars = unserialize($serialized);
        $vars['adviceMethod'] = $this->getDeclareErrorAdvice();
        foreach ($vars as $key=>$value) {
            $this->$key = $value;
        }
    }

    /**
     * Returns an advice
     *
     * @return callable
     */
    private function getDeclareErrorAdvice()
    {
        static $adviceMethod = null;
        if (!$adviceMethod) {
            $adviceMethod = function ($object, $reflector, $message, $level = E_USER_NOTICE) {
                $class   = is_string($object) ? $object : get_class($object);
                $name    = $reflector->getName();
                $message = vsprintf('[AOP Declare Error]: %s has an error: "%s"', array(
                    $class . '->' . $name,
                    $message
                ));
                trigger_error($message, $level);
            };
        }

        return $adviceMethod;
    }

    /**
     * Implement this method to perform extra treatments before and
     * after the invocation. Polite implementations would certainly
     * like to invoke {@link Joinpoint::proceed()}.
     *
     * @param MethodInvocation $invocation the method invocation joinpoint
     * @return mixed the result of the call to {@link Joinpoint::proceed()},
     * might be intercepted by the interceptor.
     */
    public function invoke(MethodInvocation $invocation)
    {
        $this->adviceMethod->__invoke(
            $invocation->getThis(),
            $invocation->getMethod(),
            $this->message,
            $this->level
        );

        return $invocation->proceed();
    }
}
