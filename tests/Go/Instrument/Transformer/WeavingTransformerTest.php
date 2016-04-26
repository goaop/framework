<?php

namespace Go\Instrument\Transformer;

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Features;
use Go\Core\AspectContainer;
use Go\Core\AdviceMatcher;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;

class WeavingTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var WeavingTransformer
     */
    protected $transformer;

    /**
     * @var StreamMetaData|null
     */
    protected $metadata = null;

    /**
     * @var null|\TokenReflection\Broker
     */
    protected $broker = null;

    /**
     * @var null|AspectKernel|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $kernel = null;

    /**
     * @var null|AdviceMatcher
     */
    protected $adviceMatcher = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $container = $this->getContainerMock();
        $reader    = $this->getMock(Reader::class);
        $loader    = $this->getMock(AspectLoader::class, [], array($container, $reader));

        $this->broker = new \TokenReflection\Broker(
            new \TokenReflection\Broker\Backend\Memory()
        );
        $this->adviceMatcher = $this->getAdviceMatcherMock();
        $this->kernel        = $this->getKernelMock(
            array(
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => null,
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => []
            ),
            $container
        );
        $this->transformer   = new WeavingTransformer(
            $this->kernel,
            $this->broker,
            $this->adviceMatcher,
            $this->getMock(CachePathManager::class, [], array($this->kernel)),
            $loader
        );

        if (defined("HHVM_VERSION")) {
            // Workaround for https://github.com/facebook/hhvm/issues/2485
            $stream = fopen(__FILE__, 'r');
            stream_filter_append($stream, 'string.tolower');
        } else {
            $stream = fopen('php://filter/string.tolower/resource=' . __FILE__, 'r');
        }
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    /**
     * It's a caution check that multiple namespaces are not yet supported
     */
    public function testMultipleNamespacesInOneFile()
    {
        $this->metadata->source = $this->loadTest('multiple-ns');
        $this->transformer->transform($this->metadata);

        $actual   = $this->normalizeWhitespaces($this->metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('multiple-ns-woven'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for code without classes
     */
    public function testEmptyNamespaceInFile()
    {
        $source = $this->loadTest('empty-classes');
        $this->metadata->source = $source;
        $this->transformer->transform($this->metadata);
        $this->assertEquals($source, $this->metadata->source);
    }

    /**
     * Do not make anything for interface class
     */
    public function testInterfaceIsSkipped()
    {
        $source = $this->loadTest('interface');
        $this->metadata->source = $source;
        $this->transformer->transform($this->metadata);
        $this->assertEquals($source, $this->metadata->source);
    }

    /**
     * Do not make anything for aspect class
     */
    public function testAspectIsSkipped()
    {
        $source = $this->loadTest('aspect');
        $this->metadata->source = $source;
        $this->transformer->transform($this->metadata);
        $this->assertEquals($source, $this->metadata->source);
    }

    /**
     * Main test case for class
     */
    public function testWeaverForNormalClass()
    {
        $this->metadata->source = $this->loadTest('class');
        $this->transformer->transform($this->metadata);

        $actual   = $this->normalizeWhitespaces($this->metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('class-woven'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check that weaver can work with final class
     */
    public function testWeaverForFinalClass()
    {
        $this->metadata->source = $this->loadTest('final-class');
        $this->transformer->transform($this->metadata);

        $actual   = $this->normalizeWhitespaces($this->metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('final-class-woven'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Transformer verifies include paths
     */
    public function testTransformerWithIncludePaths()
    {
        $container = $this->getContainerMock();
        $reader    = $this->getMock(Reader::class);
        $loader    = $this->getMock(AspectLoader::class, [], array($container, $reader));

        $this->transformer = new WeavingTransformer(
            $this->getKernelMock(
                array(
                    'appDir'       => dirname(__DIR__),
                    'includePaths' => array(__DIR__),
                    'excludePaths' => []
                ),
                $container
            ),
            $this->broker,
            $this->adviceMatcher,
            $this->getMock(CachePathManager::class, [], array($this->kernel)),
            $loader
        );
        $this->metadata->source = $this->loadTest('class');
        $this->transformer->transform($this->metadata);

        $actual   = $this->normalizeWhitespaces($this->metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('class-woven'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Testcase for multiple classes (@see https://github.com/lisachenko/go-aop-php/issues/71)
     */
    public function testMultipleClasses()
    {
        $this->metadata->source = $this->loadTest('multiple-classes');
        $this->transformer->transform($this->metadata);

        $actual   = $this->normalizeWhitespaces($this->metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('multiple-classes-woven'));
        $this->assertEquals($expected, $actual);
    }

    /**
     * Normalizes string context
     *
     * @param string $value
     * @return string
     */
    protected function normalizeWhitespaces($value)
    {
        return strtr(
            preg_replace('/\s+$/m', '', $value),
            array(
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            )
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @param array $options Additional options for kernel
     * @param AspectContainer $container Container instance
     * @return \PHPUnit_Framework_MockObject_MockObject|\Go\Core\AspectKernel
     */
    protected function getKernelMock($options, $container)
    {
        $mock = $this->getMockForAbstractClass(
            AspectKernel::class,
            [],
            '',
            false,
            true,
            true,
            array('getOptions', 'getContainer', 'hasFeature')
        );
        $mock->expects($this->any())
            ->method('getOptions')
            ->will(
                $this->returnValue($options)
            );

        $mock->expects($this->any())
            ->method('getContainer')
            ->will(
                $this->returnValue($container)
            );
        return $mock;
    }

    /**
     * Returns a mock for container
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|AdviceMatcher
     */
    protected function getAdviceMatcherMock()
    {
        $mock = $this->getMock(AdviceMatcher::class, array('getAdvicesForClass'), [], '', false);
        $mock->expects($this->any())
            ->method('getAdvicesForClass')
            ->will(
                $this->returnCallback(function (\TokenReflection\ReflectionClass $refClass) {
                    $advices  = [];
                    foreach ($refClass->getMethods() as $method) {
                        $advisorId = "advisor.{$refClass->name}->{$method->name}";
                        $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = true;
                    }
                    return $advices;
                })
            );
        return $mock;
    }

    private function loadTest($name)
    {
        return file_get_contents(__DIR__ . '/_files/' . $name . '.php');
    }

    /**
     * Returns a mock for the container
     *
     * @return AspectContainer
     */
    private function getContainerMock()
    {
        $container = $this->getMock(AspectContainer::class);

        $container
            ->expects($this->any())
            ->method('getByTag')
            ->will($this->returnValueMap(array(
                array('advisor', [])
            )));

        return $container;
    }
}
