<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Stubs\First;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class NamePointcutTest extends TestCase
{
    /**
     * Tests that method matched by name directly
     */
    public function testDirectMethodMatchByName(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            'publicMethod'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that pointcut can match property
     */
    public function testCanMatchProperty(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_PROPERTY,
            'public'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionProperty(First::class, 'public'));
        $this->assertTrue($matched, "Pointcut should match this property");
    }

    /**
     * Tests that pattern is working correctly
     */
    public function testRegularPattern(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            '*Method'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is matching
     */
    public function testMultipleRegularPattern(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            'publicMethod|protectedMethod'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is using strict matching
     *
     * @link https://github.com/goaop/framework/issues/115
     */
    public function testIssue115(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            'public|Public'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }

    public function testMatchesAnyContextWithoutReflector(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            '*Method'
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Name pointcut should match statically without reflector");
    }

    public function testMatchesGivenContextWhenContextMatchingIsEnabled(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            First::class,
            true
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Name pointcut should match statically given class");

        // When context matching is enabled, it matches any methods based only on context matching, ignoring ref name.
        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionMethod(First::class, 'publicMethod')
        );
        $this->assertTrue($matched, "Pointcut should match this method");
    }


    public function testGetKind(): void
    {
        $pointcut = new NamePointcut(Pointcut::KIND_METHOD, '*Method');
        $this->assertSame(Pointcut::KIND_METHOD, $pointcut->getKind());
    }
}
