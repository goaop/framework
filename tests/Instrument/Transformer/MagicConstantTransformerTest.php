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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MagicConstantTransformerTest extends TestCase
{
    protected SyntaxTreeRewriter $rewriter;

    protected ?StreamMetaData $metadata;

    /**
    * {@inheritDoc}
    */
    public function setUp(): void
    {
        $this->rewriter = new SyntaxTreeRewriter(
            new MagicConstantTransformer(
                $this->getKernelMock([
                    'cacheDir' => __DIR__,
                    'appDir'   => dirname(__DIR__),
                ]),
            ),
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
        $this->assertSame(TransformerResult::Abstain, $this->rewriter->transform($metadata));
        $this->assertSame($expected, $metadata->source);
    }

    public function testTransformerCanResolveDirMagicConst(): void
    {
        $metadata = new StreamMetaData(self::openStream(__FILE__), '<?php echo __DIR__; ?>');
        $expected = '<?php echo \'' . __DIR__ . '\'; ?>';
        $this->assertSame(TransformerResult::Abstain, $this->rewriter->transform($metadata));
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerCanResolveFileMagicConst(): void
    {
        $metadata = new StreamMetaData(self::openStream(__FILE__), '<?php echo __FILE__; ?>');
        $expected = '<?php echo \'' . __FILE__ . '\'; ?>';
        $this->assertSame(TransformerResult::Abstain, $this->rewriter->transform($metadata));
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerDoesNotReplaceStringWithConst(): void
    {
        $metadata = new StreamMetaData(self::openStream('php://input'), '<?php echo "__FILE__"; ?>');
        $expected = '<?php echo "__FILE__"; ?>';
        $this->assertSame(TransformerResult::Abstain, $this->rewriter->transform($metadata));
        $this->assertEquals($expected, $metadata->source);
    }

    public function testTransformerWrapsReflectionFileName(): void
    {
        $source   = '<?php $class = new ReflectionClass("stdClass"); echo $class->getFileName(); ?>';
        $metadata = new StreamMetaData(self::openStream('php://input'), $source);
        $this->assertSame(TransformerResult::Abstain, $this->rewriter->transform($metadata));
        $this->assertStringEndsWith('::resolveFileName($class->getFileName()); ?>', $metadata->source);
    }

    public function testTransformerResolvesFileName(): void
    {
        $class = MagicConstantTransformer::class;
        $this->assertStringStartsWith(dirname(__DIR__), $class::resolveFileName(__FILE__));
    }

    public function testTransformerDropsProxiedSuffixFromWovenBodyFileName(): void
    {
        $class = MagicConstantTransformer::class;

        $this->assertSame(
            dirname(__DIR__) . '/Some.php',
            $class::resolveFileName(__DIR__ . '/Some' . AspectContainer::ORIGINAL_TRAIT_FILE_SUFFIX),
        );
    }

    public function testTransformerKeepsFileNameThatOnlyContainsProxiedSuffix(): void
    {
        $class = MagicConstantTransformer::class;

        // The marker is only meaningful at the very end of the file name: a class that happens
        // to carry the suffix word inside its own name must keep it
        $this->assertSame(
            dirname(__DIR__) . '/' . AspectContainer::ORIGINAL_TRAIT_SUFFIX . 'Request.php',
            $class::resolveFileName(__DIR__ . '/' . AspectContainer::ORIGINAL_TRAIT_SUFFIX . 'Request.php'),
        );
    }

    public function testResetMakesResolveFileNameConfigureFromTheBootedKernel(): void
    {
        $instanceProperty = new ReflectionProperty(AspectKernel::class, 'instance');
        $existingInstance = $instanceProperty->getValue();
        $instanceProperty->setValue(null, $this->getKernelMock([
            'cacheDir' => __DIR__ . '/_files',
            'appDir'   => __DIR__,
        ]));

        try {
            MagicConstantTransformer::reset();

            $this->assertSame(__DIR__ . '/Some.php', MagicConstantTransformer::resolveFileName(__DIR__ . '/_files/Some.php'));
        } finally {
            $instanceProperty->setValue(null, $existingInstance);
            MagicConstantTransformer::reset();
        }
    }

    public function testTransformerKeepsFileNameWithoutCacheDirectory(): void
    {
        $transformer = new MagicConstantTransformer($this->getKernelMock([
            'cacheDir' => null,
            'appDir'   => dirname(__DIR__),
        ]));
        $class = get_class($transformer);

        $this->assertSame(__FILE__, $class::resolveFileName(__FILE__));
    }

    /**
     * Nodes built outside of the parser have no token positions, so there is nothing to rewrite
     */
    public function testNodesWithoutTokenPositionsAreNotRewritten(): void
    {
        $metadata = new StreamMetaData(self::openStream(__FILE__), '<?php echo __DIR__, $r->getFileName(); ?>');
        $expected = $metadata->source;
        $rule     = new MagicConstantTransformer($this->getKernelMock(['cacheDir' => __DIR__, 'appDir' => dirname(__DIR__)]));

        $this->assertFalse($rule->rewriteNode(new Dir(), $metadata, []));
        $this->assertFalse($rule->rewriteNode(new MethodCall(new Variable('r'), 'getFileName'), $metadata, []));
        $this->assertSame($expected, $metadata->source);
    }
}
