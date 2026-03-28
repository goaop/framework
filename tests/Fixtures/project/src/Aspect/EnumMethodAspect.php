<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Attribute as Pointcut;

/**
 * Aspect that intercepts methods on the enum fixtures used by EnumWeavingTest.
 */
class EnumMethodAspect implements Aspect
{
    /**
     * Intercepts all public instance methods on any enum in Application namespace.
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\SimpleEnum->doSomething(*))")]
    public function afterSimpleEnumMethod(): void
    {
        // advice body intentionally empty — tested via weaving assertions only
    }

    /**
     * Intercepts static methods on any enum in Application namespace.
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\SimpleEnum::doSomethingStatic(*))")]
    public function afterSimpleEnumStaticMethod(): void
    {
        // advice body intentionally empty — tested via weaving assertions only
    }

    /**
     * Intercepts all public instance methods on BackedEnum.
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\BackedEnum->*(*))" )]
    public function afterBackedEnumMethod(): void
    {
        // advice body intentionally empty — tested via weaving assertions only
    }

    /**
     * Intercepts static methods on BackedEnum.
     */
    #[Pointcut\After("execution(public Go\Tests\TestProject\Application\BackedEnum::doSomethingStatic(*))")]
    public function afterBackedEnumStaticMethod(): void
    {
        // advice body intentionally empty — tested via weaving assertions only
    }
}
