<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\PathResolver;
use PHPUnit\Framework\TestCase;
use TypeError;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class FilterInjectorTransformerTest extends TestCase
{
    protected static FilterInjectorTransformer $transformer;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        if (!isset(self::$transformer)) {
            $kernelMock = $this->getKernelMock(
                [
                    'cacheDir'      => null,
                    'cacheFileMode' => 0770,
                    'appDir'        => '',
                    'debug'         => false,
                    'features'      => 0,
                ],
                $this->createMock(AspectContainer::class),
            );
            $cachePathManager = $this
                ->getMockBuilder(CachePathManager::class)
                ->setConstructorArgs([$kernelMock])
                ->getMock();
            self::$transformer = new FilterInjectorTransformer($kernelMock, 'unit.test', $cachePathManager);
        }
    }

    /**
     * Opens a read-only stream for the given URI
     *
     * @return resource
     */
    private static function openStream(string $uri = 'php://input')
    {
        $stream = fopen($uri, 'rb');
        assert($stream !== false);

        return $stream;
    }

    /**
     * Returns a mock for kernel
     *
     * @param array<string, mixed> $options
     */
    protected function getKernelMock(array $options, AspectContainer $container): AspectKernel
    {
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer'])
            ->getMock();
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
        $metadata = new StreamMetaData(self::openStream(), '<?php echo "simple test, include" . $include; ?>');
        $output   = $metadata->source;
        self::$transformer->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testSkipTransformationQuickly(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php echo "simple test, no key words" ?>');
        $output = $metadata->source;
        self::$transformer->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformInclude(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php include $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformIncludeOnce(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php include_once $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequire(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php require $class; ?>');
        self::$transformer->transform($metadata);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequireOnce(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php require_once $class; ?>');
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

    public function testCannotRewriteClassesWithToString(): void
    {
        $this->expectException(TypeError::class);
        $file   = new \SplFileInfo(__FILE__);
        // @phpstan-ignore argument.type (intentionally passes a non-string to assert the TypeError)
        $actual = FilterInjectorTransformer::rewrite($file);
        $this->assertStringEndsWith(__FILE__, $actual);
    }

    public function testCanTransformWithBraces(): void
    {
        $fileContent = file_get_contents(__DIR__ . '/_files/yii_style.php');
        $this->assertIsString($fileContent);
        $metadata    = new StreamMetaData(self::openStream(__DIR__ . '/_files/yii_style.php'), $fileContent);
        self::$transformer->transform($metadata);
        $expectedOutput = file_get_contents(__DIR__ . '/_files/yii_style_output.php');
        $this->assertEquals($expectedOutput, $metadata->source);
    }

}
