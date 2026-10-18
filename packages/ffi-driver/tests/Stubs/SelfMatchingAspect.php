<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * An aspect whose pointcut matches its own methods: aspects are never woven, whatever matches them
 */
final class SelfMatchingAspect implements Aspect
{
    #[Pointcut\Before("execution(public Go\Driver\Ffi\Stubs\SelfMatchingAspect->*(*))")]
    public function beforeOwnMethod(MethodInvocation $invocation): void {}

    public function helper(): string
    {
        return 'helper';
    }
}
