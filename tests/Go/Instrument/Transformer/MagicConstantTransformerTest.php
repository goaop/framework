<?php

declare(strict_types = 1);
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
            ])
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @return MockObject|AspectKernel
     */
    protected function getKernelMock(array $options): AspectKernel
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
                $this->returnValue($this->createMock(AspectContainer::class))
            );

        return $mock;
    }

    public function testTransformerReturnsWithoutMagicConsts(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "simple test, no magic constants" ?>');
        $expected = $metadata->source;
        $this->transformer->transform($metadata);
        $this->assertSame($expected, $metadata->source);
    }

    public function testTransformerCanResolveDirMagicConst(): void
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __DIR__; ?>');
        $expected = '<?php echo \''.__DIR__.'\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerCanResolveFileMagicConst(): void
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __FILE__; ?>');
        $expected = '<?php echo \''.__FILE__.'\'; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerDoesNotReplaceStringWithConst(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "__FILE__"; ?>');
        $expected = '<?php echo "__FILE__"; ?>';
        $this->transformer->transform($metadata);
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerWrapsReflectionFileName(): void
    {
        $source   = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), $source);
        $this->transformer->transform($metadata);
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $metadata->source);
    }

    public function testTransformerResolvesFileName(): void
    {
        /** @var $class MagicConstantTransformer */
        $class = get_class($this->transformer);
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }
}
