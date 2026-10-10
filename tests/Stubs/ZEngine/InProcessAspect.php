<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Method-execution advices for the in-process tests of the z-engine driver
 */
final class InProcessAspect implements Aspect
{
    /**
     * @var list<string>
     */
    public static array $log = [];

    #[Pointcut\Before("execution(public Go\Stubs\ZEngine\WovenTarget->*(*)) || execution(public Go\Stubs\ZEngine\WovenChild->*(*))
        || execution(public Go\Stubs\ZEngine\WovenChild::*(*))
        || execution(public Go\Stubs\ZEngine\LateTarget->*(*)) || execution(public Go\Stubs\ZEngine\WarmTarget->*(*))")]
    public function beforeMethod(MethodInvocation $invocation): void
    {
        self::$log[] = $invocation->getMethod()->class . '::' . $invocation->getMethod()->name;
    }

    #[Pointcut\Around("execution(public Go\Stubs\ZEngine\WovenTarget->greet(*))")]
    public function aroundGreet(MethodInvocation $invocation): string
    {
        return strtoupper((string) $invocation->proceed());
    }
}
