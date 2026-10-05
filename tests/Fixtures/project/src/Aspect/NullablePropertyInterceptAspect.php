<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute\Before;

class NullablePropertyInterceptAspect implements Aspect
{
    public static int $accesses = 0;

    #[Before("access(public Go\Tests\TestProject\Application\NullablePropertyDemo->*)")]
    public function beforeNullableFieldAccess(FieldAccess $access): void
    {
        self::$accesses++;
    }
}
