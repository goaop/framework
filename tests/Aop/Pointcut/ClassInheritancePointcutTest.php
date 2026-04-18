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
use Go\Stubs\First;
use Go\Stubs\FirstStatic;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Class ClassInheritancePointcutTest.
 *
 * Testing ClassInheritancePointcut functionality.
 */
class ClassInheritancePointcutTest extends TestCase
{
    public function testNonClassContextIsNotMatches(): void
    {
        $pointcut = new ClassInheritancePointcut(static::class);

        $this->assertFalse($pointcut->matches(
            new ReflectionFileNamespace(__FILE__, __NAMESPACE__)
        ));
    }

    public function testInheritedClassContextMatches(): void
    {
        $pointcut = new ClassInheritancePointcut(First::class);

        $this->assertTrue($pointcut->matches(new ReflectionClass(FirstStatic::class)));
    }

    public function testNonInheritedClassContextDoesntMatches(): void
    {
        $pointcut = new ClassInheritancePointcut(\stdClass::class);

        $this->assertFalse($pointcut->matches(new ReflectionClass(FirstStatic::class)));
    }

    public function testGetKindReturnsCorrectValue(): void
    {
        $pointcut = new ClassInheritancePointcut(self::class);
        $this->assertSame(Pointcut::KIND_CLASS, $pointcut->getKind());
    }
}