<?php

declare(strict_types=1);

namespace Go\Driver\Ffi\Stubs;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Method-execution advices for the in-process tests of the ffi driver
 */
final class InProcessAspect implements Aspect
{
    /**
     * @var list<string>
     */
    public static array $log = [];

    #[Pointcut\Before("execution(public Go\Driver\Ffi\Stubs\WovenTarget->*(*)) || execution(public Go\Driver\Ffi\Stubs\WovenChild->*(*))
        || execution(public Go\Driver\Ffi\Stubs\WovenChild::*(*))
        || execution(public Go\Driver\Ffi\Stubs\LateTarget->*(*)) || execution(public Go\Driver\Ffi\Stubs\WarmTarget->*(*))
        || execution(public Go\Driver\Ffi\Stubs\FallbackTarget->*(*))")]
    public function beforeMethod(MethodInvocation $invocation): void
    {
        self::$log[] = $invocation->getMethod()->class . '::' . $invocation->getMethod()->name;
    }

    #[Pointcut\Around("execution(public Go\Driver\Ffi\Stubs\WovenTarget->greet(*))")]
    public function aroundGreet(MethodInvocation $invocation): string
    {
        return strtoupper((string) $invocation->proceed());
    }

    /**
     * Function interception: an internal function called unqualified from the stubs' namespace
     */
    #[Pointcut\Around("execution(Go\Driver\Ffi\Stubs\str_rot13(*))")]
    public function aroundRot13(FunctionInvocation $invocation): mixed
    {
        self::$log[] = 'around str_rot13 with ' . json_encode($invocation->getArguments());

        return $invocation->proceed();
    }
}
