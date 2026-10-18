<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Tests\TestProject\Application\Salutation;
use Go\Lang\Attribute\Around;

/**
 * Aspect with a dependency that only the application container provides (see AspectContainer::setDelegate())
 */
class GreetingAspect implements Aspect
{
    public function __construct(private readonly Salutation $salutation) {}

    #[Around('execution(public Go\Tests\TestProject\Application\Greeter->greet(*))')]
    public function aroundGreet(MethodInvocation $invocation): mixed
    {
        $result = $invocation->proceed();

        return is_string($result) ? $this->salutation->text . ' ' . $result : $result;
    }
}
