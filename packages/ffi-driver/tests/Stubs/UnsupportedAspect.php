<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute as Pointcut;

/**
 * A property-access advice: the ffi driver refuses the class it matches
 */
final class UnsupportedAspect implements Aspect
{
    #[Pointcut\Before("access(public Go\Driver\Ffi\Stubs\UnsupportedTarget->counter)")]
    public function beforeCounterAccess(FieldAccess $access): void {}
}
