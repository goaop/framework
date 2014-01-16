<?php

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Aop\Support\TruePointFilter;
use PHPUnit_Framework_TestCase as TestCase;

class AdviceMatcherTest extends TestCase
{
    /**
     * @var null|AdviceMatcher
     */
    protected $adviceMatcher = null;

    /**
     * @var null|AspectContainer
     */
    protected $container = null;

    /**
     * @var array|Advisor[]
     */
    protected $advisors = array();

    protected function setUp()
    {
        $advisors = &$this->advisors;
        $advisors = array();

        $this->container = $this->getMock('Go\Core\AspectContainer');
        $this->container
            ->expects($this->any())
            ->method('getByTag')
            ->will($this->returnValueMap(array(
                array('advisor', &$this->advisors),
                array('aspect', array())
            )));

        $this->container
            ->expects($this->any())
            ->method('registerAdvisor')
            ->will($this->returnCallback(function ($advisor, $id) use (&$advisors) {
                $advisors[$id] = $advisor;
            }));

        $reader = $this->getMock('Doctrine\Common\Annotations\Reader');

        $loader = $this->getMock('Go\Core\AspectLoader', array(), array($this->container, $reader));
        $this->adviceMatcher = new AdviceMatcher($loader, $this->container);
    }

    /**
     * Verifies that empty result will be returned without aspects and advisors
     */
    public function testGetEmptyAdvicesForClass()
    {
        // by class name
        $advices = $this->adviceMatcher->getAdvicesForClass(__CLASS__);
        $this->assertEmpty($advices);

        // by reflection
        $advices = $this->adviceMatcher->getAdvicesForClass(new \ReflectionClass(__CLASS__));
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

        $advices = $this->adviceMatcher->getAdvicesForClass(__CLASS__);
        $this->assertArrayHasKey(AspectContainer::METHOD_PREFIX, $advices);
        $this->assertArrayHasKey($funcName, $advices[AspectContainer::METHOD_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::METHOD_PREFIX]);
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

        $advices = $this->adviceMatcher->getAdvicesForClass(__CLASS__);
        $this->assertArrayHasKey(AspectContainer::PROPERTY_PREFIX, $advices);
        $this->assertArrayHasKey($propName, $advices[AspectContainer::PROPERTY_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::PROPERTY_PREFIX]);
    }
}
