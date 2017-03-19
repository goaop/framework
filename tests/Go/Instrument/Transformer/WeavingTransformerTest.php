<?php
declare(strict_types = 1);

namespace Go\Instrument\Transformer;

use Doctrine\Common\Annotations\Reader;
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
     * @var null|AspectKernel|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $kernel = null;

    /**
     * @var null|AdviceMatcher
     */
    protected $adviceMatcher = null;

    /**
     * @var null|CachePathManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $cachePathManager = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $container = $this->getContainerMock();
        $reader    = $this->createMock(Reader::class);
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container, $reader])
            ->getMock();

        $this->adviceMatcher = $this->getAdviceMatcherMock();
        $this->kernel        = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => null,
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => []
            ],
            $container
        );
        $this->cachePathManager = $this
            ->getMockBuilder(CachePathManager::class)
            ->setConstructorArgs([$this->kernel])
            ->getMock();

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->adviceMatcher,
            $this->cachePathManager,
            $loader
        );
    }

    /**
     * It's a caution check that multiple namespaces are not yet supported
     */
    public function testMultipleNamespacesInOneFile()
    {
        $metadata = $this->loadTest('multiple-ns');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('multiple-ns-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for code without classes
     */
    public function testEmptyNamespaceInFile()
    {
        $metadata = $this->loadTest('empty-classes');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('empty-classes')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for interface class
     */
    public function testInterfaceIsSkipped()
    {
        $metadata = $this->loadTest('interface');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('interface')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for aspect class
     */
    public function testAspectIsSkipped()
    {
        $metadata = $this->loadTest('aspect');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('aspect')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Main test case for class
     */
    public function testWeaverForNormalClass()
    {
        $metadata = $this->loadTest('class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('class-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check that weaver can work with final class
     */
    public function testWeaverForFinalClass()
    {
        $metadata = $this->loadTest('final-class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('final-class-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Check that weaver can work with PHP7 classes
     */
    public function testWeaverForPhp7Class()
    {
        if (PHP_VERSION_ID < 50700) {
            $this->markTestSkipped("PHP7 version is required to run this test");
        }
        $metadata = $this->loadTest('php7-class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('php7-class-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Transformer verifies include paths
     */
    public function testTransformerWithIncludePaths()
    {
        $container = $this->getContainerMock();
        $reader    = $this->createMock(Reader::class);
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container, $reader])
            ->getMock();

        $this->transformer = new WeavingTransformer(
            $this->getKernelMock(
                [
                    'appDir'       => dirname(__DIR__),
                    'includePaths' => [__DIR__],
                    'excludePaths' => []
                ],
                $container
            ),
            $this->adviceMatcher,
            $this->cachePathManager,
            $loader
        );
        $metadata = $this->loadTest('class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('class-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Testcase for multiple classes (@see https://github.com/lisachenko/go-aop-php/issues/71)
     */
    public function testMultipleClasses()
    {
        $metadata = $this->loadTest('multiple-classes');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTest('multiple-classes-woven')->source);
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
            [
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            ]
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
            ['getOptions', 'getContainer', 'hasFeature']
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
        $mock = $this->createPartialMock(AdviceMatcher::class, ['getAdvicesForClass']);
        $mock->expects($this->any())
            ->method('getAdvicesForClass')
            ->will(
                $this->returnCallback(function (\ReflectionClass $refClass) {
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

    /**
     *
     *
     * @param string $name Name of the file to load
     *
     * @return StreamMetaData
     */
    private function loadTest($name)
    {
        $fileName = __DIR__ . '/_files/' . $name . '.php';

        if (defined("HHVM_VERSION")) {
            // Workaround for https://github.com/facebook/hhvm/issues/2485
            $stream = fopen($fileName, 'r');
            stream_filter_append($stream, 'string.tolower');
        } else {
            $stream = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        }
        $source   = file_get_contents($fileName);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    /**
     * Returns a mock for the container
     *
     * @return AspectContainer|\PHPUnit_Framework_MockObject_MockObject
     */
    private function getContainerMock()
    {
        $container = $this->createMock(AspectContainer::class);

        $container
            ->expects($this->any())
            ->method('getByTag')
            ->will($this->returnValueMap([
                ['advisor', []]
            ]));

        return $container;
    }
}
