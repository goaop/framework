<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute as Pointcut;

/**
 * Intercepts promoted constructor properties (issue #599).
 */
class PromotedPropertyInterceptAspect implements Aspect
{
    #[Pointcut\Before("access(private Go\Tests\TestProject\Application\PromotedPropertyClass->name)")]
    public function beforePromotedNameAccess(FieldAccess $access): void
    {
        // No-op: registration is asserted by functional tests
    }

    #[Pointcut\Before("access(public Go\Tests\TestProject\Application\SingleLinePromotedClass->tag)")]
    public function beforePromotedTagAccess(FieldAccess $access): void
    {
        // No-op: registration is asserted by functional tests
    }

    #[Pointcut\Before("access(private Go\Tests\TestProject\Application\NewInInitializerClass->bag)")]
    public function beforeNewInInitializerBagAccess(FieldAccess $access): void
    {
        // No-op: registration is asserted by functional tests
    }
}
