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
 * Logging aspect
 *
 * @see http://go.aopphp.com/blog/2013/07/21/implementing-logging-aspect-with-doctrine-annotations/
 */
class LoggingAspect implements Aspect
{

    /**
     * This advice intercepts an execution of loggable methods
     *
     * We use "Before" type of advice to log only class name, method name and arguments before
     * method execution.
     * You can choose your own logger, for example, monolog or log4php.
     * Also you can choose "After" or "Around" advice to access an return value from method.
     *
     * To inject logger into this aspect you can look at Warlock framework with DI+AOP
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Before("@execution(Demo\Annotation\Loggable)")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        echo 'Calling Before Interceptor for ',
             $invocation,
             ' with arguments: ',
             json_encode($invocation->getArguments()),
             PHP_EOL;
    }
}
