<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Tests\TestProject\Application\ClassUsingTrait;
use Go\Tests\TestProject\Application\ClassWithPrivateMethods;

/**
 * Functional tests for trait composition weaving and private/protected method interception.
 *
 * These tests cover two important scenarios that were either impossible or untested
 * with the old extend-based proxy engine:
 *
 * 1. **Trait composition**: when a class uses a trait, methods defined in that trait
 *    must be woven into the proxy exactly like methods declared directly in the class.
 *
 * 2. **Non-public method interception**: the trait-based proxy engine can intercept
 *    private and protected methods; the old engine could not (PHP forbids overriding
 *    private methods in subclasses).
 */
class TraitCompositionTest extends BaseFunctionalTestCase
{
    // -------------------------------------------------------------------------
    // Trait composition
    // -------------------------------------------------------------------------

    /**
     * A class that uses a trait is itself woven — the proxy exists.
     */
    public function testClassUsingTraitIsWoven(): void
    {
        $this->assertClassIsWoven(ClassUsingTrait::class);
    }

    /**
     * A method defined in a used trait (not in the class body itself) must appear in
     * the proxy's $__joinPoints and be woven by the matching aspect.
     */
    public function testMethodDefinedInUsedTraitIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassUsingTrait::class,
            'doSomeTraitBehavior',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod'
        );
    }

    /**
     * A protected method defined in a used trait is also woven.
     */
    public function testProtectedMethodDefinedInUsedTraitIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassUsingTrait::class,
            'getProtectedTraitState',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod'
        );
    }

    /**
     * A method declared directly in the class (not in the trait) is still woven normally
     * even when the class also uses a trait.
     */
    public function testOwnMethodOfClassUsingTraitIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassUsingTrait::class,
            'ownMethod',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod'
        );
    }

    // -------------------------------------------------------------------------
    // Private / protected method interception
    // -------------------------------------------------------------------------

    /**
     * A class with private/protected methods is woven — the proxy exists.
     */
    public function testClassWithPrivateMethodsIsWoven(): void
    {
        $this->assertClassIsWoven(ClassWithPrivateMethods::class);
    }

    /**
     * Private method interception: the trait-based engine can intercept private methods
     * by aliasing them as `private __aop__<method>` in the proxy trait-use block.
     * This was impossible with the old extend-based engine.
     */
    public function testPrivateMethodIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassWithPrivateMethods::class,
            'doPrivate',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod'
        );
    }

    /**
     * Protected method interception works alongside private method interception.
     */
    public function testProtectedMethodIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassWithPrivateMethods::class,
            'doProtected',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod'
        );
    }

    /**
     * Public methods are not targeted by the afterNonPublicMethod advice (which uses
     * private|protected visibility), so they must not appear in its advice list.
     */
    public function testPublicMethodIsNotWovenByNonPublicAspect(): void
    {
        $this->assertMethodNotWoven(
            ClassWithPrivateMethods::class,
            'publicEntry',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod'
        );
    }
}
