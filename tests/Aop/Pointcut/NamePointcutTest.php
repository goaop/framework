<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use ArrayObject;
use Go\Stubs\First;
use Go\Stubs\FirstStatic;
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
            'publicMethod',
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
            'public',
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
            '*Method',
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
            'publicMethod|protectedMethod',
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
            'public|Public',
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched = $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }

    /**
     * Star is any characters of one name part, including none: `get*` is also `get`, `Foo*` is also `Foo`
     */
    public function testStarMatchesEmptyString(): void
    {
        $method  = new ReflectionMethod(First::class, 'publicMethod');
        $context = new ReflectionClass(First::class);

        $this->assertTrue((new NamePointcut(Pointcut::KIND_METHOD, 'publicMethod*'))->matches($context, $method));
        $this->assertTrue((new NamePointcut(Pointcut::KIND_METHOD, '*publicMethod'))->matches($context, $method));
        $this->assertTrue((new NamePointcut(Pointcut::KIND_ALL, First::class . '*', true))->matches($context));
        $this->assertTrue((new NamePointcut(Pointcut::KIND_ALL, First::class . '*', true))->matches(new ReflectionClass(FirstStatic::class)));
    }

    /**
     * Star never crosses a namespace separator, double star does
     */
    public function testStarStaysInsideOneNamePart(): void
    {
        $context = new ReflectionClass(First::class);

        $this->assertFalse((new NamePointcut(Pointcut::KIND_ALL, 'Go*', true))->matches($context));
        $this->assertFalse((new NamePointcut(Pointcut::KIND_ALL, 'Go\\*', true))->matches($context));
        $this->assertTrue((new NamePointcut(Pointcut::KIND_ALL, 'Go\\*\\First', true))->matches($context));
        $this->assertTrue((new NamePointcut(Pointcut::KIND_ALL, 'Go\\**', true))->matches($context));
    }

    /**
     * Alternatives belong to the name part they are written in: `Go\Stubs\First|FirstStatic` is
     * `Go\Stubs\First` or `Go\Stubs\FirstStatic`, not `Go\Stubs\First` or a global class `FirstStatic`
     */
    public function testAlternativesAreScopedToTheirNamePart(): void
    {
        $pointcut = new NamePointcut(Pointcut::KIND_ALL, 'Go\\Stubs\\First|FirstStatic', true);

        $this->assertTrue($pointcut->matches(new ReflectionClass(First::class)));
        $this->assertTrue($pointcut->matches(new ReflectionClass(FirstStatic::class)));

        // ArrayObject is a global class: it must not match the alternative of a nested name part
        $globalPointcut = new NamePointcut(Pointcut::KIND_ALL, 'Go\\Stubs\\First|ArrayObject', true);
        $this->assertFalse($globalPointcut->matches(new ReflectionClass(ArrayObject::class)));
        $this->assertTrue($globalPointcut->matches(new ReflectionClass(First::class)));
    }

    /**
     * Alternatives of an inner name part keep the rest of the namespace on both sides
     */
    public function testAlternativesInTheMiddleOfAName(): void
    {
        $pointcut = new NamePointcut(Pointcut::KIND_ALL, 'Go\\Stubs|Aop\\First', true);

        $this->assertTrue($pointcut->matches(new ReflectionClass(First::class)));
        $this->assertFalse($pointcut->matches(new ReflectionClass(FirstStatic::class)));
        $this->assertFalse($pointcut->matches(new ReflectionClass(ArrayObject::class)));
    }

    public function testMatchesAnyContextWithoutReflector(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            '*Method',
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Name pointcut should match statically without reflector");
    }

    public function testMatchesGivenContextWhenContextMatchingIsEnabled(): void
    {
        $pointcut = new NamePointcut(
            Pointcut::KIND_METHOD,
            First::class,
            true,
        );

        $matched = $pointcut->matches(new ReflectionClass(First::class));
        $this->assertTrue($matched, "Name pointcut should match statically given class");

        // When context matching is enabled, it matches any methods based only on context matching, ignoring ref name.
        $matched = $pointcut->matches(
            new ReflectionClass(First::class),
            new ReflectionMethod(First::class, 'publicMethod'),
        );
        $this->assertTrue($matched, "Pointcut should match this method");
    }


    public function testGetKind(): void
    {
        $pointcut = new NamePointcut(Pointcut::KIND_METHOD, '*Method');
        $this->assertSame(Pointcut::KIND_METHOD, $pointcut->kind);
    }

    public function testRegexpFailureIsReportedAsSyntaxError(): void
    {
        $pointcut = new NamePointcut(Pointcut::KIND_METHOD, 'pub*Meth*d');

        $backtrackLimit = ini_set('pcre.backtrack_limit', '1');
        try {
            $this->expectException(PointcutSyntaxException::class);
            $this->expectExceptionMessage('Name pattern `pub*Meth*d` can not be matched');
            $pointcut->matches(new ReflectionClass(First::class), new ReflectionMethod(First::class, 'publicMethod'));
        } finally {
            ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
        }
    }
}
