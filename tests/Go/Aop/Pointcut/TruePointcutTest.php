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

use Go\Aop\PointFilter;
use Go\Aop\Support\TruePointFilter;
use PHPUnit\Framework\TestCase;

class TruePointcutTest extends TestCase
{
    protected TruePointcut $pointcut;

    public function setUp(): void
    {
        $this->pointcut = new TruePointcut();
    }

    public function testItAlwaysMatchesForAnything()
    {
        $this->assertTrue($this->pointcut->matches(null));
        $this->assertTrue($this->pointcut->matches(new \ReflectionClass(self::class)));
    }

    public function testItMatchesWithDefaultKinds()
    {
        $kind = $this->pointcut->getKind();
        $this->assertTrue((bool)($kind & PointFilter::KIND_METHOD));
        $this->assertTrue((bool)($kind & PointFilter::KIND_PROPERTY));
        $this->assertTrue((bool)($kind & PointFilter::KIND_CLASS));
        $this->assertTrue((bool)($kind & PointFilter::KIND_TRAIT));
        $this->assertTrue((bool)($kind & PointFilter::KIND_FUNCTION));
        $this->assertTrue((bool)($kind & PointFilter::KIND_INIT));
        $this->assertTrue((bool)($kind & PointFilter::KIND_STATIC_INIT));
    }

    public function testItDoesNotMatchWithDynamicKindByDefault()
    {
        $kind = $this->pointcut->getKind();
        $this->assertFalse((bool)($kind & PointFilter::KIND_DYNAMIC));
    }

    public function testItUsesTruePointFilterForClass()
    {
        $this->assertInstanceOf(TruePointFilter::class, $this->pointcut->getClassFilter());
    }
}
