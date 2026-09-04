<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MagicConstantTransformerTest extends TestCase
{
    protected MagicConstantTransformer $transformer;

    protected ?StreamMetaData $metadata;

    /**
    * {@inheritDoc}
    */
    public function setUp(): void
    {
        $this->transformer = new MagicConstantTransformer(
            $this->getKernelMock([
                'cacheDir' => __DIR__,
                'appDir'   => dirname(__DIR__),
            ]),
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @param array<string, mixed> $options
     *
     * @return MockObject|AspectKernel
     */
    protected function getKernelMock(array $options): AspectKernel
    {
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer'])
            ->getMock();
        $mock->method('getOptions')
            ->willReturn($options);
        $mock->method('getContainer')
            ->willReturn($this->createMock(AspectContainer::class));

        return $mock;
    }

    /**
     * Opens a read-only stream for the given URI
     *
     * @return resource
     */
    private static function openStream(string $uri)
    {
        $stream = fopen($uri, 'rb');
        assert($stream !== false);

        return $stream;
    }

    public function testTransformerReturnsWithoutMagicConsts(): void
    {
        $metadata = new StreamMetaData(self::openStream('php://input'), '<?php echo "simple test, no magic constants" ?>');
        $expected = $metadata->source;
        $this->transformer->transform($metadata);
        $this->assertSame($expected, $metadata->source);
    }

    public function testTransformerCanResolveDirMagicConst(): void
    {
        $metadata = new StreamMetaData(self::openStream(__FILE__), '<?php echo __DIR__; ?>');
        $expected = '<?php echo \'' . __DIR__ . '\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerCanResolveFileMagicConst(): void
    {
        $metadata = new StreamMetaData(self::openStream(__FILE__), '<?php echo __FILE__; ?>');
        $expected = '<?php echo \'' . __FILE__ . '\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerDoesNotReplaceStringWithConst(): void
    {
        $metadata = new StreamMetaData(self::openStream('php://input'), '<?php echo "__FILE__"; ?>');
        $expected = '<?php echo "__FILE__"; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerWrapsReflectionFileName(): void
    {
        $source   = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $metadata = new StreamMetaData(self::openStream('php://input'), $source);
        $this->transformer->transform($metadata);
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $metadata->source);
    }

    public function testTransformerResolvesFileName(): void
    {
        $class = get_class($this->transformer);
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }

    public function testTransformerDropsProxiedSuffixFromWovenBodyFileName(): void
    {
        $class = get_class($this->transformer);

        $this->assertSame(
            dirname(__DIR__) . '/Some.php',
            $class::resolveFileName(__DIR__ . '/Some' . AspectContainer::AOP_PROXIED_SUFFIX . '.php'),
        );
    }

    public function testTransformerKeepsFileNameThatOnlyContainsProxiedSuffix(): void
    {
        $class = get_class($this->transformer);

        // The marker is only meaningful at the very end of the file name: a class that happens
        // to carry the suffix word inside its own name must keep it
        $this->assertSame(
            dirname(__DIR__) . '/' . AspectContainer::AOP_PROXIED_SUFFIX . 'Request.php',
            $class::resolveFileName(__DIR__ . '/' . AspectContainer::AOP_PROXIED_SUFFIX . 'Request.php'),
        );
    }
}
