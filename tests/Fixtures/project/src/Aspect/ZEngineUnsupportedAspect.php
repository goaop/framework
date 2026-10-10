<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Attribute as Pointcut;

/**
 * A property-access advice: the z-engine driver refuses the class it matches
 */
class ZEngineUnsupportedAspect implements Aspect
{
    #[Pointcut\Before("access(public Go\Tests\TestProject\Application\ZEngineUnsupported->counter)")]
    public function beforeCounterAccess(FieldAccess $access): void
    {
        echo 'counter accessed';
    }
}
