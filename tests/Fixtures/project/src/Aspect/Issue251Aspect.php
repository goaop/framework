<?php
declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Records the advice executions on Issue251ReportController, whose ancestors can't be located
 * (goaop/parser-reflection#251)
 */
class Issue251Aspect implements Aspect
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    #[Pointcut\Around('execution(public Go\Tests\TestProject\Partial\Issue251ReportController->*(*))')]
    public function aroundControllerMethod(MethodInvocation $invocation): mixed
    {
        self::$calls[] = 'around:' . $invocation->getMethod()->name;

        return $invocation->proceed();
    }
}
