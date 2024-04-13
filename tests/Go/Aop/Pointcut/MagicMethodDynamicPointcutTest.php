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
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class MagicMethodDynamicPointcutTest extends TestCase
{
    public function testMatchesExactDynamicMethodName(): void
    {
        $pointcut = new MagicMethodDynamicPointcut('test');

        // Statically should match any class with magic methods inside
        $matched = $pointcut->matches(new ReflectionClass(ClassWithMagicMethods::class));
        $this->assertTrue($matched, "MagicMethodDynamicPointcut should match classes with magic methods");

        // Pointcut should statically match __call magic method in the class
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call')
        );
        $this->assertTrue($matched, "Pointcut should match __call method because it is magic");

        // Pointcut should statically match __callStatic magic method in the class
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__callStatic')
        );
        $this->assertTrue($matched, "Pointcut should match __callStatic method because it is magic");

        // During dynamic matching, it should match arguments from corresponding magic calls
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call'),
            new ClassWithMagicMethods(),
            ['test']
        );
        $this->assertTrue($matched, "Pointcut should dynamically match 'test' method because it matches");
    }

    public function testDoesntMatchExactDynamicMethodName(): void
    {
        $pointcut = new MagicMethodDynamicPointcut('another');

        // During dynamic matching, it should not match dynamic method name
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call'),
            new ClassWithMagicMethods(),
            ['test']
        );
        $this->assertFalse($matched, "Pointcut should not dynamically match 'test' method because we expect 'another'");
    }


    public function testDoesntMatchWrongContextOrReflectorGiven(): void
    {
        $pointcut = new MagicMethodDynamicPointcut('test');

        // Unsupported context (ReflectionFileNamespace)
        $matched = $pointcut->matches(new ReflectionFileNamespace(__FILE__, __NAMESPACE__));
        $this->assertFalse($matched, "MagicMethodDynamicPointcut should not match ReflectionFileNamespace statically");

        // Non-magic static method
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, 'notMagicMethod')
        );
        $this->assertFalse($matched, "MagicMethodDynamicPointcut should not match non-magic method");

        // Attempt to match property with magic name
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionProperty(ClassWithMagicMethods::class, '__call')
        );
        $this->assertFalse($matched, "MagicMethodDynamicPointcut should not match property with magic name");

        // Pointcut should not match statically for __callMe magic method in the class
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__callMe')
        );
        $this->assertFalse($matched, "MagicMethodDynamicPointcut should not match __callMe method");

        // During dynamic matching, attempt to match without arguments
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call'),
            new ClassWithMagicMethods(),
        );
        $this->assertFalse($matched, "Pointcut should not dynamically match 'test' method without info about args");

        // During dynamic matching, attempt to match with empty arguments
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call'),
            new ClassWithMagicMethods(),
            []
        );
        $this->assertFalse($matched, "Pointcut should not dynamically match 'test' method without info about args");

        // During dynamic matching, attempt to match arguments with wrong type
        $matched = $pointcut->matches(
            new ReflectionClass(ClassWithMagicMethods::class),
            new ReflectionMethod(ClassWithMagicMethods::class, '__call'),
            new ClassWithMagicMethods(),
            [new \stdClass()]
        );
        $this->assertFalse($matched, "Pointcut should not dynamically match 'test' method without info about args");

    }

    public function testGetKind(): void
    {
        $pointcut = new MagicMethodDynamicPointcut('test');

        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_DYNAMIC) > 0, 'Pointcut should be dynamic');
        $this->assertTrue(($pointcut->getKind() & Pointcut::KIND_METHOD) > 0, 'Pointcut should be for methods');
    }
}
