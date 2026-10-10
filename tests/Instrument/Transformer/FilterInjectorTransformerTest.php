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
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\TestCase;
use TypeError;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class FilterInjectorTransformerTest extends TestCase
{
    protected static FilterInjectorTransformer $transformer;

    protected static SyntaxTreeRewriter $rewriter;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        // The rewriting configuration is static and set-once: every test starts from its own
        FilterInjectorTransformer::reset();
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
        self::$rewriter    = new SyntaxTreeRewriter(self::$transformer);
    }

    public static function tearDownAfterClass(): void
    {
        FilterInjectorTransformer::reset();
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
        self::$rewriter->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testSkipTransformationQuickly(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php echo "simple test, no key words" ?>');
        $output = $metadata->source;
        self::$rewriter->transform($metadata);
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformInclude(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php include $class; ?>');
        self::$rewriter->transform($metadata);
        $output = '<?php include \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformIncludeOnce(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php include_once $class; ?>');
        self::$rewriter->transform($metadata);
        $output = '<?php include_once \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequire(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php require $class; ?>');
        self::$rewriter->transform($metadata);
        $output = '<?php require \\' . get_class(self::$transformer) . '::rewrite($class, __DIR__); ?>';
        $this->assertEquals($output, $metadata->source);
    }

    public function testCanTransformRequireOnce(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php require_once $class; ?>');
        self::$rewriter->transform($metadata);
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

    /**
     * Configures the rewriting with a cache directory and the given cache manager
     */
    private function configureWithCache(CachePathManager $cachePathManager, bool $debug = false): void
    {
        FilterInjectorTransformer::reset();
        new FilterInjectorTransformer(
            $this->getKernelMock(
                ['cacheDir' => '/cache', 'appDir' => '/app', 'debug' => $debug, 'features' => 0],
                $this->createStub(AspectContainer::class),
            ),
            'unit.test.cache',
            $cachePathManager,
        );
    }

    public function testProductionIncludesSourceRecordedAsUntransformedNatively(): void
    {
        $resolvedPath     = PathResolver::realpath(__DIR__ . '/_files/class.php');
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->expects($this->once())->method('findIncludeFile')->with($resolvedPath)->willReturnArgument(0);
        $cachePathManager->expects($this->never())->method('getCachePathForResource');
        $this->configureWithCache($cachePathManager);

        // The relative resource is resolved first, the original file is then included without the filter
        $this->assertSame($resolvedPath, FilterInjectorTransformer::rewrite('_files/class.php', __DIR__));
    }

    public function testProductionResolvesRelativeSegmentsOfAbsolutePathBeforeLookingItUp(): void
    {
        $resolvedPath     = PathResolver::realpath(__DIR__ . '/_files/class.php');
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->expects($this->once())->method('findIncludeFile')->with($resolvedPath)->willReturnArgument(0);
        $this->configureWithCache($cachePathManager);

        $this->assertSame($resolvedPath, FilterInjectorTransformer::rewrite(__DIR__ . '/../Transformer/./_files/class.php'));
    }

    public function testProductionIncludesRecordedCacheFileOfWovenSourceWithoutCheckingIt(): void
    {
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->expects($this->once())->method('findIncludeFile')->with('/app/src/woven.php')->willReturn('/cache/src/woven.php');
        $cachePathManager->expects($this->never())->method('getCachePathForResource');
        $this->configureWithCache($cachePathManager);

        // The recorded cache file is trusted as it is: it does not even exist here
        $this->assertSame('/cache/src/woven.php', FilterInjectorTransformer::rewrite('/app/src/woven.php'));
    }

    public function testProductionIncludesUnknownSourceThroughTheFilter(): void
    {
        $cachePathManager = $this->createStub(CachePathManager::class);
        $cachePathManager->method('findIncludeFile')->willReturn(null);
        $cachePathManager->method('getCachePathForResource')->willReturn('/cache/src/unknown.php');
        $this->configureWithCache($cachePathManager);

        $this->assertSame(
            FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test.cache/resource=/app/src/unknown.php',
            FilterInjectorTransformer::rewrite('/app/src/unknown.php'),
        );
    }

    public function testDebugModeIncludesKnownSourceThroughTheFilter(): void
    {
        $cachePathManager = $this->createMock(CachePathManager::class);
        $cachePathManager->expects($this->never())->method('findIncludeFile');
        $cachePathManager->method('getCachePathForResource')->willReturn('/cache/src/woven.php');
        $this->configureWithCache($cachePathManager, debug: true);

        $this->assertSame(
            FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test.cache/resource=/app/src/woven.php',
            FilterInjectorTransformer::rewrite('/app/src/woven.php'),
        );
    }

    public function testResetForgetsTheConfiguredFilter(): void
    {
        FilterInjectorTransformer::reset();
        new FilterInjectorTransformer(
            $this->getKernelMock(['cacheDir' => null, 'appDir' => '', 'debug' => false, 'features' => 0], $this->createStub(AspectContainer::class)),
            'unit.test.reset',
            $this->createStub(CachePathManager::class),
        );

        $this->assertSame(
            FilterInjectorTransformer::PHP_FILTER_READ . 'unit.test.reset/resource=/path/to/my/class.php',
            FilterInjectorTransformer::rewrite('/path/to/my/class.php'),
        );
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
        self::$rewriter->transform($metadata);
        $expectedOutput = file_get_contents(__DIR__ . '/_files/yii_style_output.php');
        $this->assertEquals($expectedOutput, $metadata->source);
    }

    /**
     * Nodes built outside of the parser have no token positions, so there is nothing to rewrite
     */
    public function testNodesWithoutTokenPositionsAreNotRewritten(): void
    {
        $metadata = new StreamMetaData(self::openStream(), '<?php include $class; ?>');
        $expected = $metadata->source;

        $this->assertFalse(self::$transformer->rewriteNode(new Include_(new Variable('class'), Include_::TYPE_INCLUDE), $metadata, []));
        $this->assertFalse(self::$transformer->rewriteNode(new String_('not an include'), $metadata, []));
        $this->assertSame($expected, $metadata->source);
    }
}
