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

use Go\Aop\PointFilter;
use Go\Aop\Support\NotPointFilter;
use Go\Aop\Support\TruePointFilter;
use Go\Stubs\First;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

class SignaturePointcutTest extends TestCase
{
    /**
     * Tests that method matched by name directly
     */
    public function testDirectMethodMatchByName(): void
    {
        $pointcut = new SignaturePointcut(
            PointFilter::KIND_METHOD,
            'publicMethod',
            TruePointFilter::getInstance()
        );

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that pointcut can match property
     */
    public function testCanMatchProperty(): void
    {
        $pointcut = new SignaturePointcut(
            PointFilter::KIND_METHOD,
            'public',
            TruePointFilter::getInstance()
        );

        $matched = $pointcut->matches(new ReflectionProperty(First::class, 'public'));
        $this->assertTrue($matched, "Pointcut should match this property");
    }

    /**
     * Tests that pointcut won't match if modifier filter is not match
     */
    public function testWontMatchModifier(): void
    {
        $trueInstance = TruePointFilter::getInstance();
        $notInstance  = new NotPointFilter($trueInstance);
        $pointcut     = new SignaturePointcut(PointFilter::KIND_METHOD, 'publicMethod', $notInstance);
        $matched      = $pointcut->matches(new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should not match modifier");
    }

    /**
     * Tests that pattern is working correctly
     */
    public function testRegularPattern(): void
    {
        $pointcut = new SignaturePointcut(
            PointFilter::KIND_METHOD,
            '*Method',
            TruePointFilter::getInstance()
        );

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is matching
     */
    public function testMultipleRegularPattern(): void
    {
        $pointcut = new SignaturePointcut(
            PointFilter::KIND_METHOD,
            'publicMethod|protectedMethod',
            TruePointFilter::getInstance()
        );

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is using strict matching
     *
     * @link https://github.com/lisachenko/go-aop-php/issues/115
     */
    public function testIssue115(): void
    {
        $pointcut = new SignaturePointcut(
            PointFilter::KIND_METHOD,
            'public|Public',
            TruePointFilter::getInstance()
        );

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched = $pointcut->matches(new ReflectionMethod(First::class, 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }
}
