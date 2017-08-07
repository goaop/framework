<?php
declare(strict_types = 1);

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Core\GoAspectContainer;
use Go\Instrument\ClassLoading\CachePathManager;
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
            $kernelMock = $this->getKernelMock(
                [
                    'cacheDir'      => null,
                    'cacheFileMode' => 0770,
                    'appDir'        => '',
                    'debug'         => false,
                    'features'      => 0
                ],
                $this->createMock(GoAspectContainer::class)
            );
            $cachePathManager = $this
                ->getMockBuilder(CachePathManager::class)
                ->setConstructorArgs([$kernelMock])
                ->getMock();
            self::$transformer = new FilterInjectorTransformer($kernelMock, 'unit.test', $cachePathManager);
        }
        $stream = fopen('php://input', 'r');
        $this->metadata = new StreamMetaData($stream);
        fclose($stream);
    }

    /**
     * Returns a mock for kernel
     *
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
            ['getOptions', 'getContainer']
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
                . stream_resolve_include_path(__DIR__ . '/_files/class.php');
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
