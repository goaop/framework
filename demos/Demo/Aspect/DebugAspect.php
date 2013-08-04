<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\Pointcut;
use Go\Lang\Annotation\DeclareParents;
use Go\Lang\Annotation\DeclareError;

/**
 * Debug aspect
 */
class DebugAspect implements Aspect
{
    /**
     * Message to show when calling the method
     *
     * @var string
     * @DeclareError("execution(public Demo\Example\General->cacheMe(*))")
     */
    protected $message = 'Method should not be called in debug mode';

    /**
     * @DeclareParents(
     *   value="class(Demo\Example\General)",
     *   interface="Serializable",
     *   defaultImpl="Demo\Aspect\Introduce\SerializableImpl"
     * )
     *
     * @var null
     */
    protected $introduction = null;

    /**
     * Pointcut for example class
     *
     * @Pointcut("execution(public Demo\Example\General->*(*))")
     */
    protected function examplePublicMethods() {}

    /**
     * Method that should be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("$this->examplePublicMethods")
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
             PHP_EOL;
    }

    /**
     * Method that should be called after real method
     *
     * @param MethodInvocation $invocation Invocation
     * @After("$this->examplePublicMethods")
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
             PHP_EOL;
    }

    /**
     * Cacheable methods
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@annotation(Demo\Annotation\Cacheable)")
     */
    public function aroundCacheable(MethodInvocation $invocation)
    {
        static $memoryCache = array();

        $time  = microtime(true);

        $obj   = $invocation->getThis();
        $class = is_object($obj) ? get_class($obj) : $obj;
        $key   = $class . ':' . $invocation->getMethod()->name;
        if (!isset($memoryCache[$key])) {
            $memoryCache[$key] = $invocation->proceed();
        }

        echo "Take ", sprintf("%0.3f", (microtime(true) - $time) * 1e3), "ms to call method", PHP_EOL;
        return $memoryCache[$key];
    }

    /**
     * Method that should be called around property
     *
     * @param FieldAccess $property Joinpoint
     *
     * @Around("access(* Demo\Example\General->*)")
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
            PHP_EOL;

        return $value;
    }

    /**
     * @param FunctionInvocation $invocation
     *
     * @Around("execution(Demo\Example\*(*))")
     *
     * @return mixed
     */
    public function aroundFunction(FunctionInvocation $invocation)
    {
        echo 'Calling Around Interceptor for function: ',
            $invocation->getFunction()->getName(),
            '()',
            ' with arguments: ',
            json_encode($invocation->getArguments()),
            PHP_EOL;

        return $invocation->proceed();
    }
}
