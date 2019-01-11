<?php
declare(strict_types = 1);

namespace Go\Instrument\Transformer;

use Go\Core\AspectKernel;
use Go\Core\GoAspectContainer;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use PHPUnit\Framework\TestCase;

class FilterInjectorTransformerTest extends TestCase
{
    /**
     * @var FilterInjectorTransformer
     */
    protected static $transformer;

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
        $mock
            ->method('getOptions')
            ->willReturn($options);

        $mock
            ->method('getContainer')
            ->willReturn($container);
        return $mock;
    }

    public function testCanTransformWithoutInclusion(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "simple test, include" . $include; ?>');
        $output   = $metadata->source;
        self::$transformer->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testSkipTransformationQuickly(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "simple test, no key words" ?>');
        $output = $metadata->source;
        self::$transformer->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformInclude(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php include $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformIncludeOnce(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php include_once $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequire(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php require $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequireOnce(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php require_once $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php require_once \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanRewriteWithFilter(): void
    {
        $actualPath   = FilterInjectorTransformer::rewrite('/path/to/my/class.php');
        $expectedPath = FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test/resource=/path/to/my/class.php';
        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testCanRewriteRelativePathsWithFilter(): void
    {
        $actualPath   = FilterInjectorTransformer::rewrite('_files/class.php', __DIR__);
        $expectedPath = FilterInjectorTransformer::PHP_FILTER_READ
                . 'unit.test/resource='
                . PathResolver::realpath(__DIR__ . '/_files/class.php');
        $this->assertEquals($expectedPath, $actualPath);
    }

    public function testCanRewriteClassesWithToString(): void
    {
        $file   = new \SplFileInfo(__FILE__);
        $actual = FilterInjectorTransformer::rewrite($file);
        $this->assertStringEndsWith(__FILE__, $actual);
    }

    public function testCanTransformWithBraces(): void
    {
        $fileContent = file_get_contents(__DIR__ . '/_files/yii_style.php');
        $metadata    = new StreamMetaData(fopen(__DIR__ . '/_files/yii_style.php', 'r'), $fileContent);
        self::$transformer->transform($metadata);
        $expectedOutput = file_get_contents(__DIR__ . '/_files/yii_style_output.php');
        $this->assertEquals($expectedOutput, $metadata->source);
    }

}
