<?php

namespace Go\Instrument\Transformer;

use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\StreamMetaData;

class FilterInjectorTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FilterInjectorTransformer
     */
    protected static $transformer;

    /**
     * @var StreamMetaData|null
     */
    protected $metadata = null;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {
        if (!self::$transformer) {
            self::$transformer = new FilterInjectorTransformer(
                array(
                    'cacheDir' => null,
                    'appDir' => '',
                    'debug' => false,
                ),
                'unit.test'
            );
        }
        $stream = fopen('php://input', 'r');
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    public function testCanTransformWithoutInclusion()
    {
        $this->metadata->source = '<?php echo "simple test, include" . $include; ?>';
        $output = $this->metadata->source;
        self::$transformer->transform($this->metadata);
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testSkipTransformationQuickly()
    {
        $this->metadata->source = '<?php echo "simple test, no key words" ?>';
        $output = $this->metadata->source;
        self::$transformer->transform($this->metadata);
        $this->assertEquals($this->metadata->source, $output);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCanBeConfiguredOnlyOnce()
    {
        $filter = new FilterInjectorTransformer(array(), 'test');
    }

    public function testCanTransformInclude()
    {
        $this->metadata->source = '<?php include $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformIncludeOnce()
    {
        $this->metadata->source = '<?php include_once $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformRequire()
    {
        $this->metadata->source = '<?php require $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformRequireOnce()
    {
        $this->metadata->source = '<?php require_once $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php require_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanRewriteWithFilter()
    {
        $this->metadata->source = FilterInjectorTransformer::rewrite('/path/to/my/class.php');
        $output = FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test/resource=/path/to/my/class.php';
        $this->assertEquals($this->metadata->source, $output);
    }
}
