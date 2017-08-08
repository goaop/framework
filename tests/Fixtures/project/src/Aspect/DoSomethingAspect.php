<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation as Pointcut;

class DoSomethingAspect implements Aspect
{
    /**
     * Intercepts doSomething()
     *
     * @param MethodInvocation $invocation
     *
     * @Pointcut\After("execution(public Go\Tests\TestProject\Application\*->doSomething(*)) || execution(public Go\Tests\TestProject\Application\*->doSomethingElse(*))")
     */
    public function afterDoSomething()
    {
        echo 'It does something else after something is done.';
    }
}
