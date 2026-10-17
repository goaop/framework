<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

/**
 * Aspect with a constructor dependency, built by the application container (see AspectContainer::addDelegatedService())
 */
class GreetingAspect implements Aspect
{
    public function __construct(private readonly string $salutation) {}

    #[Around('execution(public Go\Tests\TestProject\Application\Greeter->greet(*))')]
    public function aroundGreet(MethodInvocation $invocation): mixed
    {
        $result = $invocation->proceed();

        return is_string($result) ? $this->salutation . ' ' . $result : $result;
    }
}
