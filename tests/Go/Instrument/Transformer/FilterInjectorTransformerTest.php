<?php

namespace Go\Instrument\Transformer;

use Go\Instrument\Transformer\FilterInjectorTransformer;

class FilterInjectorTransformerTest extends \PHPUnit_Framework_TestCase
{
    protected static $transformer;

    public function setUp()
    {
        if(null != self::$transformer) {
            return;
        }
        self::$transformer = new FilterInjectorTransformer(array(
            'cacheDir' => null,
            'appDir' => '',
            'debug' => false,
        ), '');
    }

    public function testCanTransformeWithoutInclusion()
    {
        $source = '<?php echo "simple test, include" . $include; ?>';
        $output = $source;
        $source = self::$transformer->transform($source);
        $this->assertEquals($source, $output);
    }
    
    public function testCanTransformeInclude()
    {
        $source = '<?php include $class; ?>';
        $source = self::$transformer->transform($source);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeIncludeOnce()
    {
        $source = '<?php include_once $class; ?>';
        $source = self::$transformer->transform($source);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeRequire()
    {
        $source = '<?php require $class; ?>';
        $source = self::$transformer->transform($source);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeRequireOnce()
    {
        $source = '<?php require_once $class; ?>';
        $source = self::$transformer->transform($source);
        $output = '<?php require_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanRewriteWithFilter()
    {
        $transformer = self::$transformer;
        $source = $transformer::rewrite('/path/to/my/class.php');
        $output = $transformer::PHP_FILTER_READ . '/resource=/path/to/my/class.php';
        $this->assertEquals($source, $output);
    }
}
