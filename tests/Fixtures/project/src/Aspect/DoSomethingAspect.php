<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Attribute as Pointcut;

class DoSomethingAspect implements Aspect
{
    /**
     * Intercepts doSomething()
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\*->doSomething(*)) || execution(public Go\Tests\TestProject\Application\*->doSomethingElse(*))")]
    public function afterDoSomething()
    {
        echo 'It does something else after something is done.';
    }
}
