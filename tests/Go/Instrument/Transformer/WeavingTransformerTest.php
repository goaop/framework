<?php

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;

use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\StreamMetaData;

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
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->transformer = new WeavingTransformer(
            $this->getKernelMock(
                array(
                    'cacheDir'     => __DIR__,
                    'appDir'       => dirname(__DIR__),
                    'includePaths' => array(),
                    'autoload'     => array()
                ),
                $this->getContainerMock()
            ),
            new \TokenReflection\Broker(
                new \TokenReflection\Broker\Backend\Memory()
            )
        );

        $stream = fopen('php://filter/string.tolower/resource=' . __FILE__, 'r');
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    /**
     * It's a caution check that multiple namespaces are not yet supported
     *
     * @expectedException \PHPUnit_Framework_Error_Warning
     */
    public function testMultipleNamespacesInOneFile()
    {
        $this->metadata->source = $this->loadTest('multiple-ns');
        $this->transformer->transform($this->metadata);
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
        $source   = $this->loadTest('class');
        $this->metadata->source = $source;
        $this->transformer->transform($this->metadata);
        $actual = strtr(
            preg_replace('/^\s+$/m', '', $this->metadata->source),
            array(
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            )
        );
        $expected = strtr(
            preg_replace('/^\s+$/m', '', $this->loadTest('class-woven')),
            array(
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            )
        );
        $this->assertEquals($expected, $actual);
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
            'Go\Core\AspectKernel',
            array(),
            '',
            false,
            true,
            true,
            array('getOptions', 'getContainer')
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
     * @return \PHPUnit_Framework_MockObject_MockObject|AspectContainer
     */
    protected function getContainerMock()
    {
        $mock = $this->getMock(
            'Go\Core\AspectContainer',
            array('getAdvicesForClass')
        );
        $mock->expects($this->any())
            ->method('getAdvicesForClass')
            ->will(
                $this->returnCallback(function (\TokenReflection\ReflectionClass $refClass) {
                    $advices  = array();
                    foreach ($refClass->getMethods() as $method) {
                        $advices[AspectContainer::METHOD_PREFIX . ':' . $method->name] = true;
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
}
