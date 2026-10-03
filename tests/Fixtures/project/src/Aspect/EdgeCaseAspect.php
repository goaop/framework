<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

/**
 * Counts the calls of every public method of EdgeCaseDemo while passing their results through
 */
class EdgeCaseAspect implements Aspect
{
    public static int $calls = 0;

    #[Around("execution(public Go\Tests\TestProject\Application\EdgeCaseDemo->*(*))")]
    public function aroundEdgeCase(MethodInvocation $invocation): mixed
    {
        self::$calls++;

        return $invocation->proceed();
    }
}
