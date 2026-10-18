<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute as Pointcut;

/**
 * A property-access advice: the ffi driver refuses the class it matches
 */
class FfiUnsupportedAspect implements Aspect
{
    #[Pointcut\Before("access(public Go\Tests\TestProject\Application\FfiUnsupported->counter)")]
    public function beforeCounterAccess(FieldAccess $access): void
    {
        echo 'counter accessed';
    }
}
