<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Stubs\ClassWithMagicMethods;
use Go\Stubs\FirstStatic;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

class MatchInheritedPointcutTest extends TestCase
{
    public function testMatchesInheritedMethods(): void
    {
        $pointcut = new MatchInheritedPointcut();

        // Statically should match any class
        $matched = $pointcut->matches(new ReflectionClass(FirstStatic::class));
        $this->assertTrue($matched, "MatchInheritedPointcut should match any class statically");

        // Pointcut should statically match dynamic parent method from the First::class
        $matched = $pointcut->matches(
            new ReflectionClass(FirstStatic::class),
            new ReflectionMethod(FirstStatic::class, 'publicMethod')
        );
        $this->assertTrue($matched, "MatchInheritedPointcut should match inherited `publicMethod` method");

        // Pointcut should statically match static parent method from the First::class
        $matched = $pointcut->matches(
            new ReflectionClass(FirstStatic::class),
            new ReflectionMethod(FirstStatic::class, 'staticSelfProtected')
        );
        $this->assertTrue($matched, "MatchInheritedPointcut should match inherited `staticSelfProtected` method");
    }

    public function testMatchesInheritedProperties(): void
    {
        $pointcut = new MatchInheritedPointcut();

        // Pointcut should statically match parent property from the First::class
        $matched = $pointcut->matches(
            new ReflectionClass(FirstStatic::class),
            new ReflectionProperty(FirstStatic::class, 'public')
        );
        $this->assertTrue($matched, "MatchInheritedPointcut should match inherited `public` property");

        // Pointcut should statically match static protected parent property from the First::class
        $matched = $pointcut->matches(
            new ReflectionClass(FirstStatic::class),
            new ReflectionProperty(FirstStatic::class, 'protected')
        );
        $this->assertTrue($matched, "MatchInheritedPointcut should match inherited `protected` property");
    }

    public function testDoesntMatchNonInheritedMethods(): void
    {
        $pointcut = new MatchInheritedPointcut();

        // Pointcut should not statically match method from the FirstStatic::class itself
        $matched = $pointcut->matches(
            new ReflectionClass(FirstStatic::class),
            new ReflectionMethod(FirstStatic::class, 'init')
        );
        $this->assertFalse($matched, "MatchInheritedPointcut should not match declared `init` method");
    }

    public function testDoesntMatchWrongContext(): void
    {
        $pointcut = new MatchInheritedPointcut();

        // Unsupported context (ReflectionFileNamespace)
        $matched = $pointcut->matches(new ReflectionFileNamespace(__FILE__, __NAMESPACE__));
        $this->assertFalse($matched, "MatchInheritedPointcut should not match ReflectionFileNamespace statically");

        // Attempt to match function
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionFunction('var_dump')
        );
        $this->assertFalse($matched, "MatchInheritedPointcut should not match function as reflector");
    }

    public function testGetKind(): void
    {
        $pointcut = new MatchInheritedPointcut();

        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_PROPERTY) > 0, 'Pointcut should be for properties');
        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_METHOD) > 0, 'Pointcut should be for methods');
    }
}
