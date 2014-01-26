<?php
/**
 * Go! AOP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */


namespace Go\Aop\Pointcut;

use Go\Aop\Support\NotPointFilter;
use Go\Aop\Support\TruePointFilter;

class SignatureMethodPointcutTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Tests that method matched by name directly
     */
    public function testDirectMethodMatchByName()
    {
        $pointcut = new SignatureMethodPointcut('publicMethod', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that pointcut won't match property
     */
    public function testWontMatchProperty()
    {
        $pointcut = new SignatureMethodPointcut('public', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionProperty('Go\Tests\First', 'public'));
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
        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should not match modifier");
    }

    /**
     * Tests that pattern is working correctly
     */
    public function testRegularPattern()
    {
        $pointcut = new SignatureMethodPointcut('*Method', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'protectedMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");
    }

    /**
     * Tests that multiple pattern is matching
     */
    public function testMultipleRegularPattern()
    {
        $pointcut = new SignatureMethodPointcut('publicMethod|protectedMethod', TruePointFilter::getInstance());
        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'publicMethod'));
        $this->assertTrue($matched, "Pointcut should match this method");

        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'protectedMethod'));
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
        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'publicMethod'));
        $this->assertFalse($matched, "Pointcut should match strict");

        $matched  = $pointcut->matches(new \ReflectionMethod('Go\Tests\First', 'staticLsbPublic'));
        $this->assertFalse($matched, "Pointcut should match strict");
    }
}