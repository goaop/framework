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
use Go\Lang\Annotation\Around;

/**
 * Fluent interface aspect provides an easy way to reuse "chain" interface for classes
 *
 * Basically, it uses around method to intercept all public setters in the class that implements
 * special marker interface FluentInterface. Then it checks the return value for setter, if it's null,
 * then advice replaces it with reference to the object "$this".
 *
 * @see http://go.aopphp.com/blog/2013/03/19/implementing-fluent-interface-pattern-in-php/
 */
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

        return $result !== null ? $result : $invocation->getThis();
    }
}
