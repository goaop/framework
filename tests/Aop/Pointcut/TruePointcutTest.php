<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class TruePointcutTest extends TestCase
{
    protected TruePointcut $pointcut;

    public function setUp(): void
    {
        $this->pointcut = new TruePointcut();
    }

    public function testItAlwaysMatchesForAnything(): void
    {
        $this->assertTrue($this->pointcut->matches(new ReflectionClass(self::class)));
        $this->assertTrue(
            $this->pointcut->matches(
                new ReflectionClass(self::class),
                new ReflectionMethod(self::class, __FUNCTION__)
            )
        );
    }

    public function testItMatchesWithDefaultKinds(): void
    {
        $kind = $this->pointcut->getKind();
        $this->assertTrue((bool)($kind & Pointcut::KIND_METHOD));
        $this->assertTrue((bool)($kind & Pointcut::KIND_PROPERTY));
        $this->assertTrue((bool)($kind & Pointcut::KIND_CLASS));
        $this->assertTrue((bool)($kind & Pointcut::KIND_TRAIT));
        $this->assertTrue((bool)($kind & Pointcut::KIND_FUNCTION));
        $this->assertTrue((bool)($kind & Pointcut::KIND_INIT));
        $this->assertTrue((bool)($kind & Pointcut::KIND_STATIC_INIT));
    }

    public function testItDoesNotMatchWithDynamicKindByDefault(): void
    {
        $kind = $this->pointcut->getKind();
        $this->assertFalse((bool)($kind & Pointcut::KIND_DYNAMIC));
    }
}
