<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Go\Aop\Pointcut;

use Go\Aop\Support\NotPointFilter;
use Go\Aop\Support\TruePointFilter;

class SignatureMethodPointcutTest extends \PHPUnit_Framework_TestCase
{
    const STUB_CLASS = 'Go\Stubs\First';

    /**
     * Tests that method matched by name directly
     */
    public function testDirectMethodMatchByName()
    {
        $pointcut = new SignatureMethodPointcut('publicMethod', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that pointcut won't match property
     */
    public function testWontMatchProperty()
    {
        $pointcut = new SignatureMethodPointcut('public', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionProperty(self::STUB_CLASS, 'public'));
        $this->assertFalse($matched, "Pointcut should not match this property");
    }

    /**
     * Tests that pointcut won't match if modifier filter is not match
     */
    public function testWontMatchModifier()
    {
        $trueInstance = TruePointFilter::getInstance();
        $notInstance  = new NotPointFilter($trueInstance);
        $pointcut = new SignatureMethodPointcut('publicMethod', $notInstance);
        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should not match modifier");
    }

    /**
     * Tests that pattern is working correctly
     */
    public function testRegularPattern()
    {
        $pointcut = new SignatureMethodPointcut('*Method', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is matching
     */
    public function testMultipleRegularPattern()
    {
        $pointcut = new SignatureMethodPointcut('publicMethod|protectedMethod', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
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
        $pointcut = new SignatureMethodPointcut('public|Public', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched  = $pointcut->matches(new \ReflectionMethod(self::STUB_CLASS, 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }
}