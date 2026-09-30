<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect\Alpha;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Shares its short name with the aspect of the sibling namespace (issue #668)
 */
class CollisionAspect implements Aspect
{
    #[Pointcut\Before("execution(public Go\Tests\TestProject\Application\ImportCollisionClass->write(*))")]
    public function beforeWrite(MethodInvocation $invocation): void
    {
        echo 'A;';
    }
}
