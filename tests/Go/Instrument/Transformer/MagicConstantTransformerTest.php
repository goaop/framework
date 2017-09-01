<?php

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\StreamMetaData;

class MagicConstantTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MagicConstantTransformer
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
        $this->transformer = new MagicConstantTransformer(
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

    public function testTransformerReturnsWithoutMagicConsts()
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "simple test, no magic constants" ?>');
        $expected = $metadata->source;
        $this->transformer->transform($metadata);
        $this->assertSame($expected, $metadata->source);
    }

    public function testTransformerCanResolveDirMagicConst()
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __DIR__; ?>');
        $expected = '<?php echo \''.__DIR__.'\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerCanResolveFileMagicConst()
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __FILE__; ?>');
        $expected = '<?php echo \''.__FILE__.'\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerDoesNotReplaceStringWithConst()
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "__FILE__"; ?>');
        $expected = '<?php echo "__FILE__"; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerWrapsReflectionFileName()
    {
        $source   = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), $source);
        $this->transformer->transform($metadata);
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $metadata->source);
    }

    public function testTransformerResolvesFileName()
    {
        /** @var $class MagicConstantTransformer */
        $class = get_class($this->transformer);
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }
}
