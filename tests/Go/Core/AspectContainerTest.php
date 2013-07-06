<?php

namespace Go\Core;

use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Aop\Support\TruePointFilter;
use \PHPUnit_Framework_TestCase as TestCase;

class AspectContainerTest extends TestCase
{
    /**
     * @var null|AspectContainer
     */
    protected $container = null;

    protected function setUp()
    {
        $this->container = new AspectContainer();
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

    /**
     * Verifies that empty result will be returned without aspects and advisors
     */
    public function testGetEmptyAdvicesForClass()
    {
        // by class name
        $advices = $this->container->getAdvicesForClass(__CLASS__);
        $this->assertEmpty($advices);

        // by reflection
        $advices = $this->container->getAdvicesForClass(new \ReflectionClass(__CLASS__));
        $this->assertEmpty($advices);
    }

    /**
     * Check that list of advices for method works correctly
     */
    public function testGetSingleMethodAdviceForClassFromAdvisor()
    {
        $funcName = __FUNCTION__;

        $pointcut = $this->getMock('Go\Aop\Pointcut');
        $pointcut
            ->expects($this->any())
            ->method('getClassFilter')
            ->will($this->returnValue(TruePointFilter::getInstance()));
        $pointcut
            ->expects($this->any())
            ->method('matches')
            ->will($this->returnCallback(function ($point) use ($funcName) {
                return $point->name === $funcName;
            }));
        $pointcut
            ->expects($this->any())
            ->method('getKind')
            ->will($this->returnValue(Pointcut::KIND_METHOD));

        $advice = $this->getMock('Go\Aop\Advice');
        $advisor = new DefaultPointcutAdvisor($pointcut, $advice);
        $this->container->registerAdvisor($advisor, 'test');

        $advices = $this->container->getAdvicesForClass(__CLASS__);
        $this->assertArrayHasKey(AspectContainer::METHOD_PREFIX . ':' . $funcName, $advices);
        $this->assertCount(1, $advices);
    }

    /**
     * Check that list of advices for fields works correctly
     */
    public function testGetSinglePropertyAdviceForClassFromAdvisor()
    {
        $propName = 'container'; // $this->container;

        $pointcut = $this->getMock('Go\Aop\Pointcut');
        $pointcut
            ->expects($this->any())
            ->method('getClassFilter')
            ->will($this->returnValue(TruePointFilter::getInstance()));
        $pointcut
            ->expects($this->any())
            ->method('matches')
            ->will($this->returnCallback(function ($point) use ($propName) {
                return $point->name === $propName;
            }));
        $pointcut
            ->expects($this->any())
            ->method('getKind')
            ->will($this->returnValue(Pointcut::KIND_PROPERTY));

        $advice = $this->getMock('Go\Aop\Advice');
        $advisor = new DefaultPointcutAdvisor($pointcut, $advice);
        $this->container->registerAdvisor($advisor, 'test');

        $advices = $this->container->getAdvicesForClass(__CLASS__);
        $this->assertArrayHasKey(AspectContainer::PROPERTY_PREFIX . ':' . $propName, $advices);
        $this->assertCount(1, $advices);
    }
}
