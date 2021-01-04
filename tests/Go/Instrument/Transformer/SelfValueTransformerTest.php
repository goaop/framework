<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SelfValueTransformerTest extends TestCase
{
    protected SelfValueTransformer $transformer;

    protected ?StreamMetaData $metadata;

     /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        $this->transformer = new SelfValueTransformer(
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
        $mock
            ->method('getOptions')
            ->willReturn($options);

        $mock
            ->method('getContainer')
            ->willReturn($this->createMock(AspectContainer::class));

        return $mock;
    }

    public function testTransformerReplacesAllSelfPlaces(): void
    {
        $testFile = fopen(__DIR__ . '/_files/file-with-self.php', 'rb');
        $content  = stream_get_contents($testFile);
        $metadata = new StreamMetaData($testFile, $content);
        $this->transformer->transform($metadata);
        $expected = file_get_contents(__DIR__ . '/_files/file-with-self-transformed.php');
        $this->assertSame($expected, (string) $metadata->source);
    }
}
