<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute as Pointcut;

/**
 * A property-access advice: the z-engine driver refuses the class it matches
 */
final class UnsupportedAspect implements Aspect
{
    #[Pointcut\Before("access(public Go\Stubs\ZEngine\UnsupportedTarget->counter)")]
    public function beforeCounterAccess(FieldAccess $access): void {}
}
