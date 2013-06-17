<?php
/**
 * Created by JetBrains PhpStorm.
 * User: 1
 * Date: 17.03.13
 * Time: 17:27
 * To change this template use File | Settings | File Templates.
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

class FluentInterfaceAspect implements Aspect
{
    /**
     * Fluent interface advice
     *
     * @Around("within(Demo\Aspect\FluentInterface+) && execution(public **->set*(*))")
     *
     * @param MethodInvocation $invocation
     * @return mixed|null|object
     */
    protected function aroundMethodExecution(MethodInvocation $invocation)
    {
        $result = $invocation->proceed();
        return $result!==null ? $result : $invocation->getThis();
    }
}