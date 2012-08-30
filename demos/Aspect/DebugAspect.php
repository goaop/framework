<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\Pointcut;

/**
 * Debug aspect
 */
class DebugAspect implements Aspect
{
    /**
     * Message to show when calling the method
     *
     * @var string
     */
    protected $message = '';

    /**
     * Aspect constructor
     *
     * @param string $message Additional message to show
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Pointcut for example class
     *
     * @Pointcut("execution(public Example->*(*))")
     */
    protected function examplePublicMethods() {}

    /**
     * Method that should be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("execution(public Example->*(*))")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $obj = $invocation->getThis();
        echo 'Calling Before Interceptor for method: ',
             is_object($obj) ? get_class($obj) : $obj,
             $invocation->getMethod()->isStatic() ? '::' : '->',
             $invocation->getMethod()->getName(),
             '()',
             ' with arguments: ',
             json_encode($invocation->getArguments()),
             "<br>\n";
    }

    /**
     * Method that should be called after real method
     *
     * @param MethodInvocation $invocation Invocation
     * @After("execution(public Example->*(*))")
     */
    public function afterMethodExecution(MethodInvocation $invocation)
    {
        $obj = $invocation->getThis();
        echo 'Calling After Interceptor for method: ',
             is_object($obj) ? get_class($obj) : $obj,
             $invocation->getMethod()->isStatic() ? '::' : '->',
             $invocation->getMethod()->getName(),
             '()',
             ' with arguments: ',
             json_encode($invocation->getArguments()),
             "<br>\n";
    }

    /**
     * Method that should be called around property
     *
     * @param FieldAccess $property Joinpoint
     *
     * Around("get(* Example->*)")
     * Around("set(* Example->*)")
     *
     * @return mixed
     */
    public function aroundFieldAccess(FieldAccess $property)
    {
        $type = $property->getAccessType() === FieldAccess::READ ? 'read' : 'write';
        $value = $property->proceed();
        echo
            "Calling Around Interceptor for field: ",
            get_class($property->getThis()),
            "->",
            $property->getField()->getName(),
            ", access: $type",
            ", value: ",
            json_encode($value),
            "<br>\n";

        return $value;
    }
}
