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

class SignaturePointcutTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = First::class;

    /**
     * Tests that method matched by name directly
     */
    public function testDirectMethodMatchByName()
    {
        $filterKind = PointFilter::KIND_METHOD;
        $pointcut   = new SignaturePointcut($filterKind, 'publicMethod', TruePointFilter::getInstance());
        $matched    = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that pointcut can match property
     */
    public function testCanMatchProperty()
    {
        $filterKind = PointFilter::KIND_METHOD;
        $pointcut   = new SignaturePointcut($filterKind, 'public', TruePointFilter::getInstance());
        $matched    = $pointcut->matches(new \ReflectionProperty(self::STUB_CLASS, 'public'));
        $this->assertTrue($matched, "Pointcut should match this property");
    }

    /**
     * Tests that pointcut won't match if modifier filter is not match
     */
    public function testWontMatchModifier()
    {
        $trueInstance = TruePointFilter::getInstance();
        $notInstance  = new NotPointFilter($trueInstance);
        $filterKind   = PointFilter::KIND_METHOD;

        $pointcut     = new SignaturePointcut($filterKind, 'publicMethod', $notInstance);
        $matched      = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should not match modifier");
    }

    /**
     * Tests that pattern is working correctly
     */
    public function testRegularPattern()
    {
        $filterKind = PointFilter::KIND_METHOD;
        $pointcut   = new SignaturePointcut($filterKind, '*Method', TruePointFilter::getInstance());
        $matched    = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is matching
     */
    public function testMultipleRegularPattern()
    {
        $filterKind = PointFilter::KIND_METHOD;
        $pointcut   = new SignaturePointcut($filterKind, 'publicMethod|protectedMethod', TruePointFilter::getInstance());
        $matched    = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is using strict matching
     *
     * @link https://github.com/lisachenko/go-aop-php/issues/115
     */
    public function testIssue115()
    {
        $filterKind = PointFilter::KIND_METHOD;
        $pointcut   = new SignaturePointcut($filterKind, 'public|Public', TruePointFilter::getInstance());
        $matched    = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }
}
