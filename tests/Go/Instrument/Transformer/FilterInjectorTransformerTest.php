<?php

namespace Go\Instrument\Transformer;

use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\StreamMetaData;

class FilterInjectorTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected $metadata;
    protected static $instance;
    protected $transformer;

    protected static function getInstance()
    {
        if(null === self::$instance) {
            self::$instance = new FilterInjectorTransformer(array(
                'cacheDir' => null,
                'appDir' => '',
                'debug' => false,
            ), '');
        }
        return self::$instance;
    }
    
    public function setUp()
    {
        $this->transformer = self::getInstance();
        $this->metadata = new StreamMetaData(fopen('php://input', 'r'));
    }

    public function testCanTransformeWithoutInclusion()
    {
        $this->metadata->source = '<?php echo "simple test, include" . $include; ?>';
        $output = $this->metadata->source;
        $this->transformer->transform($this->metadata);
        $this->assertEquals($this->metadata->source, $output);
    }
    
    public function testCanTransformeInclude()
    {
        $this->metadata->source = '<?php include $class; ?>';
        $this->transformer->transform($this->metadata);
        $output = '<?php include \\' . get_class($this->transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformeIncludeOnce()
    {
        $this->metadata->source = '<?php include_once $class; ?>';
        $this->transformer->transform($this->metadata);
        $output = '<?php include_once \\' . get_class($this->transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformeRequire()
    {
        $this->metadata->source = '<?php require $class; ?>';
        $this->transformer->transform($this->metadata);
        $output = '<?php require \\' . get_class($this->transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanTransformeRequireOnce()
    {
        $this->metadata->source = '<?php require_once $class; ?>';
        $this->transformer->transform($this->metadata);
        $output = '<?php require_once \\' . get_class($this->transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($this->metadata->source, $output);
    }

    public function testCanRewriteWithFilter()
    {
        $this->metadata->source = FilterInjectorTransformer::rewrite('/path/to/my/class.php');
        $output = FilterInjectorTransformer::PHP_FILTER_READ . '/resource=/path/to/my/class.php';
        $this->assertEquals($this->metadata->source, $output);
    }
}
