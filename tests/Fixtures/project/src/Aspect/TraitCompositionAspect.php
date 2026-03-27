<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Lang\Attribute as Pointcut;

/**
 * Aspect for functional tests covering:
 *  1. Trait composition — methods defined in a used trait are woven into the using class
 *  2. Private/protected method interception — new capability of the trait-based proxy engine
 */
class TraitCompositionAspect implements Aspect
{
    /**
     * Intercepts all public/protected methods of ClassUsingTrait, including those
     * defined in BehaviorTrait via trait composition.
     */
    #[Pointcut\After("execution(public|protected Go\Tests\TestProject\Application\ClassUsingTrait->*(*))")]
    public function afterClassUsingTraitMethod(): void {}

    /**
     * Intercepts private and protected methods of ClassWithPrivateMethods.
     * Private method interception was impossible with the old extend-based proxy engine
     * but is fully supported by the trait-based engine.
     */
    #[Pointcut\After("execution(private|protected Go\Tests\TestProject\Application\ClassWithPrivateMethods->*(*))")]
    public function afterNonPublicMethod(): void {}
}
