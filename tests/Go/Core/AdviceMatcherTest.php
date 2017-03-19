<?php
declare(strict_types = 1);

namespace Go\Core;

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Aop\Pointcut;
use Go\Aop\Support\DefaultPointcutAdvisor;
use Go\Aop\Support\TruePointFilter;
use Go\ParserReflection\Locator\ComposerLocator;
use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use PHPUnit_Framework_TestCase as TestCase;

class AdviceMatcherTest extends TestCase
{
    /**
     * @var null|AdviceMatcher
     */
    protected $adviceMatcher = null;

    protected $reflectionClass = null;

    /**
     * This method is called before the first test of this test class is run.
     *
     * @since Method available since Release 3.4.0
     */
    public static function setUpBeforeClass()
    {
        ReflectionEngine::init(new ComposerLocator());
    }

    protected function setUp()
    {
        $container = $this->createMock(AspectContainer::class);
        $reader    = $this->createMock(Reader::class);
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container, $reader])
            ->getMock();

        $this->adviceMatcher = new AdviceMatcher($loader, $container);

        $reflectionFile        = new ReflectionFile(__FILE__);
        $this->reflectionClass = $reflectionFile->getFileNamespace(__NAMESPACE__)->getClass(__CLASS__);
    }

    /**
     * Verifies that empty result will be returned without aspects and advisors
     */
    public function testGetEmptyAdvicesForClass()
    {
        // by reflection
        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, []);
        $this->assertEmpty($advices);
    }

    /**
     * Check that list of advices for method works correctly
     */
    public function testGetSingleMethodAdviceForClassFromAdvisor()
    {
        $funcName = __FUNCTION__;

        $pointcut = $this->createMock(Pointcut::class);
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

        $advice = $this->createMock(Advice::class);
        $advisor = new DefaultPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, [$advisor]);
        $this->assertArrayHasKey(AspectContainer::METHOD_PREFIX, $advices);
        $this->assertArrayHasKey($funcName, $advices[AspectContainer::METHOD_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::METHOD_PREFIX]);
    }

    /**
     * Check that list of advices for fields works correctly
     */
    public function testGetSinglePropertyAdviceForClassFromAdvisor()
    {
        $propName = 'adviceMatcher'; // $this->adviceMatcher;

        $pointcut = $this->createMock(Pointcut::class);
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

        $advice = $this->createMock(Advice::class);
        $advisor = new DefaultPointcutAdvisor($pointcut, $advice);

        $advices = $this->adviceMatcher->getAdvicesForClass($this->reflectionClass, [$advisor]);
        $this->assertArrayHasKey(AspectContainer::PROPERTY_PREFIX, $advices);
        $this->assertArrayHasKey($propName, $advices[AspectContainer::PROPERTY_PREFIX]);
        $this->assertCount(1, $advices[AspectContainer::PROPERTY_PREFIX]);
    }
}
