<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Before;

/**
 * Aspect that intercepts specific magic methods, declared with __call and __callStatic
 */
class DynamicMethodsAspect implements Aspect
{

    /**
     * This advice intercepts an execution of __call methods
     *
     * Unlike traditional "execution" pointcut, "dynamic" is checking the name of method in
     * the runtime, allowing to write interceptors for __call more transparently.
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Before("dynamic(public Demo\Example\DynamicMethodsDemo->save*(*))")
     */
    public function beforeMagicMethodExecution(MethodInvocation $invocation)
    {
        $obj = $invocation->getThis();

        // we need to unpack args from invocation args
        list($methodName, $args) = $invocation->getArguments();
        echo 'Calling Magic Interceptor for method: ',
            is_object($obj) ? get_class($obj) : $obj,
            $invocation->getMethod()->isStatic() ? '::' : '->',
            $methodName,
            '()',
            ' with arguments: ',
            json_encode($args),
            PHP_EOL;
    }

    /**
     * This advice intercepts an execution of methods via __callStatic
     *
     * @param MethodInvocation $invocation
     * @Before("dynamic(public Demo\Example\DynamicMethodsDemo::find*(*))")
     */
    public function beforeMagicStaticMethodExecution(MethodInvocation $invocation)
    {
        // we need to unpack args from invocation args
        list($methodName) = $invocation->getArguments();

        echo "Calling Magic Static Interceptor for method: ", $methodName, PHP_EOL;
    }
}
