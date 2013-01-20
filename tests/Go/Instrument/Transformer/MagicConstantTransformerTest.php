<?php

namespace Go\Instrument\Transformer;

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
    protected $metadata = null;

     /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        $this->transformer = new MagicConstantTransformer(array(
            'cacheDir' => __DIR__,
            'appDir'   => dirname(__DIR__),
        ));

        $stream = fopen('php://filter/string.tolower/resource=' . __FILE__, 'r');
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    public function testTransformerReturnsWithoutMagicConsts()
    {
        $this->metadata->source = '<?php echo "simple test, no magic constants" ?>';
        $expected = $this->metadata->source;
        $this->transformer->transform($this->metadata);
        $this->assertSame($expected, $this->metadata->source);
    }

    public function testTransformerCanResolveDirMagicConst()
    {
        $this->metadata->source = '<?php echo __DIR__; ?>';
        $expected = '<?php echo \''.__DIR__.'\'; ?>';
        $this->transformer->transform($this->metadata);
        $this->assertEquals($expected, $this->metadata->source);
    }

    public function testTransformerCanResolveFileMagicConst()
    {
        $this->metadata->source = '<?php echo __FILE__; ?>';
        $expected = '<?php echo \''.__FILE__.'\'; ?>';
        $this->transformer->transform($this->metadata);
        $this->assertEquals($expected, $this->metadata->source);
    }

    public function testTransformerDoesNotReplaceStringWithConst()
    {
        $expected = '<?php echo "__FILE__"; ?>';
        $this->metadata->source = $expected;
        $this->transformer->transform($this->metadata);
        $this->assertEquals($expected, $this->metadata->source);
    }

    public function testTransformerWrapsReflectionFileName()
    {
        $this->metadata->source = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $this->transformer->transform($this->metadata);
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $this->metadata->source);
    }

    public function testTransformerResolvesFileName()
    {
        /** @var $class MagicConstantTransformer */
        $class = get_class($this->transformer);
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }
}
