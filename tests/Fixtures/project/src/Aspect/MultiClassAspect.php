<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Advises the classes declared together in MultiClassHolder.php (issue #760)
 */
class MultiClassAspect implements Aspect
{
    #[Pointcut\Before(
        "execution(public Go\Tests\TestProject\Application\MultiClass*->hello(*))"
        . " || execution(public Go\Tests\TestProject\Application\Sub\MultiClass*->hello(*))",
    )]
    public function beforeHello(MethodInvocation $invocation): void
    {
        echo 'advised:';
    }
}
