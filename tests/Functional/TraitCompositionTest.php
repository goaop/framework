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
use Go\Tests\TestProject\Application\Issue761Child;
use Go\Tests\TestProject\Application\Issue761WovenOverrideTrait;
use Go\Tests\TestProject\Application\Issue761WovenOverrideTraitUser;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

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
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod',
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
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod',
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
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterClassUsingTraitMethod',
        );
    }

    /**
     * A trait method with #[\Override] can not be intercepted (issue #761), the documented workaround is to exclude
     * it with `!matchInherited()`: the class is woven for its own method, loads, and the trait method keeps its
     * behaviour without an advice.
     */
    public function testTraitMethodWithOverrideExcludedByNotMatchInherited(): void
    {
        $advisor = 'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterIssue761OwnMethod';
        $this->assertClassIsWoven(Issue761Child::class);
        $this->assertMethodWoven(Issue761Child::class, 'own', $advisor);
        $this->assertMethodNotWoven(Issue761Child::class, 'hello', $advisor);

        $output = $this->runInProject(
            sprintf(
                '$instance = new %s(); echo $instance->hello(), ",", $instance->own(), ",", '
                . '$instance instanceof \Go\Aop\Proxy ? "proxy" : "plain";',
                '\\' . Issue761Child::class,
            ),
        );
        $this->assertSame('trait,own,proxy', $output);
    }

    /**
     * A woven trait whose own intercepted method has #[\Override] (issue #761): the attribute is stripped from the
     * renamed original trait, so the class using the trait loads, and the advice runs.
     */
    public function testWovenTraitMethodWithOverrideIsIntercepted(): void
    {
        $this->assertClassIsWoven(Issue761WovenOverrideTrait::class);

        $output = $this->runInProject(
            sprintf('echo (new %s())->hello();', '\\' . Issue761WovenOverrideTraitUser::class),
        );
        $this->assertSame('advised:woven-trait', $output);
    }

    /**
     * Runs PHP code in a subprocess after the front controller of the fixture project, returns its trimmed output
     */
    private function runInProject(string $code): string
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);
        $script  = sprintf('include %s; %s', var_export($this->configuration['frontController'], true), $code);
        $process = new Process(
            [$phpExecutable, '-r', $script],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Loading the woven class failed: ' . $process->getOutput() . $process->getErrorOutput(),
        );

        return trim($process->getOutput());
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
     * by aliasing them as `private <method>OriginalAlias` in the proxy trait-use block.
     * This was impossible with the old extend-based engine.
     */
    public function testPrivateMethodIsWoven(): void
    {
        $this->assertMethodWoven(
            ClassWithPrivateMethods::class,
            'doPrivate',
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod',
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
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod',
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
            'Go\\Tests\\TestProject\\Aspect\\TraitCompositionAspect->afterNonPublicMethod',
        );
    }
}
