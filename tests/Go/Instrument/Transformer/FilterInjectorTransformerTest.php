<?php

namespace Go\Instrument\Transformer;

use Go\Instrument\Transformer\FilterInjectorTransformer;

class FilterInjectorTransformerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FilterInjectorTransformer
     */
    protected static $transformer;

    /**
     * @var StreamMetaData|null
     */
    protected static $metaData = null;

    /**
     * {@inheritDoc}
     */
    public static function setUpBeforeClass()
    {
        self::$transformer = new FilterInjectorTransformer(array(
            'cacheDir' => null,
            'appDir' => '',
            'debug' => false,
        ), 'unit.test');

        $stream = fopen(__FILE__, 'r');
        self::$metaData = new StreamMetaData($stream);
        fclose($stream);
    }

    /**
     * {@inheritDoc}
     */
    public static function tearDownAfterClass()
    {
        self::$transformer = null;
        self::$metaData    = null;
    }

    public function testCanTransformeWithoutInclusion()
    {
        $source = '<?php echo "simple test, include" . $include; ?>';
        $output = $source;
        $source = self::$transformer->transform($source, self::$metaData);
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeInclude()
    {
        $source = '<?php include $class; ?>';
        $source = self::$transformer->transform($source, self::$metaData);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeIncludeOnce()
    {
        $source = '<?php include_once $class; ?>';
        $source = self::$transformer->transform($source, self::$metaData);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeRequire()
    {
        $source = '<?php require $class; ?>';
        $source = self::$transformer->transform($source, self::$metaData);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanTransformeRequireOnce()
    {
        $source = '<?php require_once $class; ?>';
        $source = self::$transformer->transform($source, self::$metaData);
        $output = '<?php require_once \\' . get_class(self::$transformer) . '::rewrite( $class); ?>';
        $this->assertEquals($source, $output);
    }

    public function testCanRewriteWithFilter()
    {
        $transformer = self::$transformer;
        $source = $transformer::rewrite('/path/to/my/class.php');
        $output = $transformer::PHP_FILTER_READ . 'unit.test/resource=/path/to/my/class.php';
        $this->assertEquals($source, $output);
    }
}
