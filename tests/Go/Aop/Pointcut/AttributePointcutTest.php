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
use Go\Stubs\First;
use Go\Stubs\StubAttribute;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class AttributePointcutTest extends TestCase
{
    public function testMatchesClassWithAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_CLASS,
            StubAttribute::class,
            true
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Attribute pointcut should match class statically with attribute");

        // When context matching is enabled, it should also match any methods based only on context matching, ignoring ref name.
        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionMethod(First::class, 'publicMethod')
        );
        $this->assertTrue($matched, "Pointcut should match this method because annotation is matched");
    }

    public function testDoesntMatchClassWithoutAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_CLASS,
            StubAttribute::class,
            true
        );

        $matched = $pointcut->matches(new ReflectionClass(self::class));
        $this->assertFalse($matched, "Attribute pointcut should not match class statically without attribute");
    }

    public function testMatchesMethodWithAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_METHOD,
            StubAttribute::class,
        );

        // With one argument it should match statically with any given context
        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Pointcut should match this class statically even without attribute");

        $matched = $pointcut->matches(new ReflectionClass(self::class));
        $this->assertTrue($matched, "Pointcut should match this class statically even without attribute");

        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionMethod(First::class, 'publicMethodWithAttribute')
        );
        $this->assertTrue($matched, "Pointcut should match this method because annotation is matched");
    }

    public function testDoesntMatchMethodWithoutAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_METHOD,
            StubAttribute::class,
        );

        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionMethod(First::class, 'publicMethod')
        );
        $this->assertFalse($matched, "Pointcut should not match this method because annotation is not matched");
    }

    public function testMatchesPropertyWithAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_PROPERTY,
            StubAttribute::class,
        );

        // With one argument it should match statically with any given context
        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Pointcut should match this class statically even without reflector");

        $matched = $pointcut->matches(new ReflectionClass(self::class));
        $this->assertTrue($matched, "Pointcut should match this class statically even without reflector");

        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionProperty(First::class, 'publicWithAttribute')
        );
        $this->assertTrue($matched, "Pointcut should match this property because annotation is matched");
    }

    public function testDoesntMatchPropertyWithoutAttribute(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_PROPERTY,
            StubAttribute::class,
        );

        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionProperty(First::class, 'public')
        );
        $this->assertFalse($matched, "Pointcut should not match this property because annotation is not matched");
    }

    public function testGetKind(): void
    {
        $pointcut = new AttributePointcut(
            Pointcut::KIND_CLASS,
            StubAttribute::class,
            true
        );

        $this->assertEquals(Pointcut::KIND_CLASS, $pointcut->getKind());
    }
}
