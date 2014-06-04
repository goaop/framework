<?php

namespace Go\Core;

use Go\Aop\Pointcut;
use \PHPUnit_Framework_TestCase as TestCase;

class GoAspectContainerTest extends TestCase
{
    /**
     * @var null|GoAspectContainer
     */
    protected $container = null;

    protected function setUp()
    {
        $this->container = new GoAspectContainer();
        $this->container->set('kernel.options', array());
        $this->container->set('kernel.interceptFunctions', false);
    }

    /**
     * Tests that all services are registered
     *
     * @dataProvider internalServicesList
     */
    public function testAllServicesAreConfigured($serviceId)
    {
        $service = $this->container->get($serviceId);
        $this->assertNotNull($service);
    }

    public function internalServicesList()
    {
        return array(
            array('aspect.loader'),
            array('aspect.advice_matcher'),
            array('aspect.annotation.reader'),
            array('aspect.annotation.raw.reader'),
            array('aspect.pointcut.lexer'),
            array('aspect.pointcut.parser'),
        );
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testPointcutCanBeRegisteredAndReceived()
    {
        $pointcut = $this->getMock('Go\Aop\Pointcut');
        $this->container->registerPointcut($pointcut, 'test');

        $this->assertSame($pointcut, $this->container->getPointcut('test'));
        // Verify that tag is working
        $pointcuts = $this->container->getByTag('pointcut');
        $this->assertSame(array('pointcut.test' => $pointcut), $pointcuts);
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testAdvisorCanBeRegistered()
    {
        $advisor = $this->getMock('Go\Aop\Advisor');
        $this->container->registerAdvisor($advisor, 'test');

        // Verify that tag is working
        $advisors = $this->container->getByTag('advisor');
        $this->assertSame(array('advisor.test' => $advisor), $advisors);
    }

    /**
     * Tests that aspect can be registered and accessed
     */
    public function testAspectCanBeRegisteredAndReceived()
    {
        $aspect = $this->getMock('Go\Aop\Aspect');
        $aspectClass = get_class($aspect);

        $this->container->registerAspect($aspect);

        $this->assertSame($aspect, $this->container->getAspect($aspectClass));
        // Verify that tag is working
        $aspects = $this->container->getByTag('aspect');
        $this->assertSame(array("aspect.{$aspectClass}" => $aspect), $aspects);
    }

    /**
     * Tests that container resources can be added and isFresh works correctly
     */
    public function testResourceManagement()
    {
        // Without resources this should be always true
        $isFresh = $this->container->isFresh(time());
        $this->assertTrue($isFresh);
        
        $this->container->addResource(__FILE__);
        $realMtime = filemtime(__FILE__);
        $isFresh = $this->container->isFresh($realMtime - 3600);
        $this->assertFalse($isFresh);

        $isFresh = $this->container->isFresh($realMtime + 3600);
        $this->assertTrue($isFresh);
    }
}
