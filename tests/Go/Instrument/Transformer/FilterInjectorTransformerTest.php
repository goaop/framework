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
                    'appDir'   => '',
                    'debug'    => false,
                    'features' => 0
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
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testSkipTransformationQuickly()
    {
        $this->metadata->source = '<?php echo "simple test, no key words" ?>';
        $output = $this->metadata->source;
        self::$transformer->transform($this->metadata);
        $this->assertEquals($output, $this->metadata->source);
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
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite( $class, __DIR__); ?>';
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanTransformIncludeOnce()
    {
        $this->metadata->source = '<?php include_once $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite( $class, __DIR__); ?>';
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanTransformRequire()
    {
        $this->metadata->source = '<?php require $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite( $class, __DIR__); ?>';
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanTransformRequireOnce()
    {
        $this->metadata->source = '<?php require_once $class; ?>';
        self::$transformer->transform($this->metadata);
        $output = '<?php require_once \\' . get_class(self::$transformer) . '::rewrite( $class, __DIR__); ?>';
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanRewriteWithFilter()
    {
        $this->metadata->source = FilterInjectorTransformer::rewrite('/path/to/my/class.php');
        $output = FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test/resource=/path/to/my/class.php';
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanRewriteRelativePathsWithFilter()
    {
        $this->metadata->source = FilterInjectorTransformer::rewrite('_files/class.php', __DIR__);
        $output = FilterInjectorTransformer::PHP_FILTER_READ
                . 'unit.test/resource='
                . stream_resolve_include_path('_files/class.php');
        $this->assertEquals($output, $this->metadata->source);
    }

    public function testCanRewriteClassesWithToString()
    {
        $file = new \SplFileInfo(__FILE__);
        $actual = FilterInjectorTransformer::rewrite($file);
        $this->assertStringEndsWith(__FILE__, $actual);
    }

    public function testCanTransformWithBraces()
    {
        $this->metadata->source = file_get_contents(__DIR__ . '/_files/yii_style.php');
        self::$transformer->transform($this->metadata);
        $this->assertEquals(file_get_contents(__DIR__ . '/_files/yii_style_output.php'), $this->metadata->source);
    }

}
