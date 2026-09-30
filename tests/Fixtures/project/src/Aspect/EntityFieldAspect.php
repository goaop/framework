<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Weaves the Doctrine entity of the fixture project (issue #671)
 */
class EntityFieldAspect implements Aspect
{
    #[Pointcut\Before("access(public Go\Tests\TestProject\Entity\WovenEntity->name)")]
    public function beforeNameAccess(FieldAccess $access): void
    {
        echo 'name;';
    }

    #[Pointcut\Before("execution(public Go\Tests\TestProject\Entity\WovenEntity->beforePersist(*))")]
    public function beforePersistCallback(MethodInvocation $invocation): void
    {
        echo 'persist;';
    }
}
