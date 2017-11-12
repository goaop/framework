<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Annotation as Pointcut;

class WeavingAspect implements Aspect
{
    /**
     * Intercepts method in the final class
     *
     * @Pointcut\After("execution(public Go\Tests\TestProject\Application\FinalClass->*(*))")
     */
    public function afterPublicMethodInTheFinalClass()
    {
        echo 'It intercepts methods in the final class';
    }

    /**
     * Doesn't intercept interfaces
     *
     * @Pointcut\After("execution(public Go\Tests\TestProject\Application\FooInterface->*(*))")
     */
    public function afterPublicMethodInTheInterface()
    {
        echo 'It does not intercept methods in the interface';
    }
}
