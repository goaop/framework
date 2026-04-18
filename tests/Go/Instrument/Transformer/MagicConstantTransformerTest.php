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
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\PrettyPrinter\Standard;
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
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer'])
            ->getMock();
        $mock->expects($this->any())
            ->method('getOptions')
            ->willReturn($options);
        $mock->expects($this->any())
            ->method('getContainer')
            ->willReturn($this->createMock(AspectContainer::class));

        return $mock;
    }

    public function testTransformerReturnsWithoutMagicConsts(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "simple test, no magic constants" ?>');
        $actual = $this->applyVisitor($metadata);
        $this->assertSame('<?php echo "simple test, no magic constants" ?>', $actual);
    }

    public function testTransformerCanResolveDirMagicConst(): void
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __DIR__; ?>');
        $expected = '<?php echo \''.__DIR__.'\'; ?>';
        $actual = $this->applyVisitor($metadata);
        $this->assertEquals($expected, $actual);
    }

    public function testTransformerCanResolveFileMagicConst(): void
    {
        $metadata = new StreamMetaData(fopen(__FILE__, 'rb'), '<?php echo __FILE__; ?>');
        $expected = '<?php echo \''.__FILE__.'\'; ?>';
        $actual = $this->applyVisitor($metadata);
        $this->assertEquals($expected, $actual);
    }

    public function testTransformerDoesNotReplaceStringWithConst(): void
    {
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), '<?php echo "__FILE__"; ?>');
        $expected = '<?php echo "__FILE__"; ?>';
        $actual = $this->applyVisitor($metadata);
        $this->assertEquals($expected, $actual);
    }

    public function testTransformerWrapsReflectionFileName(): void
    {
        $source   = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $metadata = new StreamMetaData(fopen('php://input', 'rb'), $source);
        $actual = $this->applyVisitor($metadata);
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $actual);
    }

    public function testTransformerResolvesFileName(): void
    {
        /** @var $class MagicConstantTransformer */
        $class = get_class($this->transformer);
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }

    private function applyVisitor(StreamMetaData $metadata): string
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        $traverser->addVisitor(new FileNameInjectorNodeVisitor($metadata));
        $traverser->addVisitor($this->transformer);
        $newAst = $traverser->traverse($metadata->syntaxTree);

        $printer = new Standard();

        return $printer->printFormatPreserving($newAst, $metadata->syntaxTree, $metadata->tokenStream);
    }
}
