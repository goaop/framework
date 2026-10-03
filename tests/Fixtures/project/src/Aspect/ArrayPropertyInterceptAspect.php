<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute\Around;

class ArrayPropertyInterceptAspect implements Aspect
{
    #[Around("access(protected Go\Tests\TestProject\Application\ArrayPropertyDemo->indirectModificationCheck)")]
    public function aroundArrayFieldAccess(FieldAccess $access): mixed
    {
        // An around advice must return the value of the field, it becomes the result of the access
        return $access->proceed();
    }
}
