<?php

declare(strict_types=1);
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
use Go\Lang\Attribute\Before;

/**
 * Aspect that intercepts magic methods, declared with __call and __callStatic
 *
 * Traditional "execution" pointcuts match the magic method itself, so the real method name
 * should be extracted from the invocation arguments and filtered inside the advice.
 */
class DynamicMethodsAspect implements Aspect
{
    /**
     * This advice intercepts an execution of __call method
     *
     * The name of the invoked method is the first invocation argument,
     * so we filter interesting methods (save*) right inside the advice.
     */
    #[Before('execution(public Demo\Example\DynamicMethodsDemo->__call(*))')]
    public function beforeMagicMethodExecution(MethodInvocation $invocation): void
    {
        // we need to unpack args from invocation args
        [$methodName, $args] = $invocation->getArguments();
        if (!str_starts_with($methodName, 'save')) {
            return;
        }
        echo 'Calling Magic Interceptor for method: ',
            $invocation->getScope(),
            '->',
            $methodName,
            '()',
            ' with arguments: ',
            json_encode($args),
            PHP_EOL;
    }

    /**
     * This advice intercepts an execution of methods via __callStatic
     */
    #[Before('execution(public Demo\Example\DynamicMethodsDemo::__callStatic(*))')]
    public function beforeMagicStaticMethodExecution(MethodInvocation $invocation): void
    {
        // we need to unpack args from invocation args
        [$methodName, $args] = $invocation->getArguments();
        if (!str_starts_with($methodName, 'find')) {
            return;
        }
        echo 'Calling Static Magic Interceptor for method: ',
            $invocation->getScope(),
            '::',
            $methodName,
            '()',
            ' with arguments: ',
            json_encode($args),
            PHP_EOL;
    }
}
