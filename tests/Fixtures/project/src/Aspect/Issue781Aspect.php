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
 * Records every advice execution on the Issue781*Gateway classes (issue #781): one entry per method execution, however
 * many classes of the hierarchy are woven
 */
class Issue781Aspect implements Aspect
{
    /**
     * @var list<string>
     */
    public static array $calls = [];

    #[Pointcut\Around('execution(public Go\Tests\TestProject\Application\Issue781*Gateway->*(*))')]
    public function aroundGatewayMethod(MethodInvocation $invocation): mixed
    {
        self::$calls[] = 'around:' . $invocation->getMethod()->name;

        return $invocation->proceed();
    }

    #[Pointcut\Before('execution(public Go\Tests\TestProject\Application\Issue781*Gateway::*(*))')]
    public function beforeGatewayStaticMethod(MethodInvocation $invocation): void
    {
        self::$calls[] = 'before:' . $invocation->getMethod()->name;
    }
}
