<?php

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;

class SelfValueTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SelfValueTransformer
     */
    protected $transformer;

    /**
     * @var StreamMetaData|null
     */
    protected $metadata;

     /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->transformer = new SelfValueTransformer(
            $this->getKernelMock([
                'cacheDir' => __DIR__,
                'appDir'   => dirname(__DIR__),
            ])
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\Go\Core\AspectKernel
     */
    protected function getKernelMock($options)
    {
        $mock = $this->getMockForAbstractClass(
            AspectKernel::class,
            [],
            '',
            false,
            true,
            true,
            ['getOptions']
        );
        $mock->expects($this->any())
            ->method('getOptions')
            ->will(
                $this->returnValue($options)
            );
        return $mock;
    }

    public function testTransformerReplacesAllSelfPlaces()
    {
        $testFile = fopen(__DIR__ . '/_files/file-with-self.php', 'rb');
        $content  = stream_get_contents($testFile);
        $metadata = new StreamMetaData($testFile, $content);
        $this->transformer->transform($metadata);
        $expected = file_get_contents(__DIR__ . '/_files/file-with-self-transformed.php');
        $this->assertSame($expected, (string) $metadata->source);
    }
}
