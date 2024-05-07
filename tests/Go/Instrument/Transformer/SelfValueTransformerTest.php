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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $mock = $this->createMock(AspectKernel::class);
        $mock
            ->method('getOptions')
            ->willReturn($options);

        $mock
            ->method('getContainer')
            ->willReturn($this->createMock(AspectContainer::class));

        return $mock;
    }

    #[DataProvider("filesDataProvider")]
    public function testTransformerProcessFiles(
        string $sourceFileWithContent,
        string $fileWithExpectedContent,
    ): void {
        try {
            $sourceFile     = fopen($sourceFileWithContent, 'rb');
            $sourceContent  = stream_get_contents($sourceFile);
            $sourceMetadata = new StreamMetaData($sourceFile, $sourceContent);
            $this->transformer->transform($sourceMetadata);

            $expected = file_get_contents($fileWithExpectedContent);
            $this->assertSame($expected, $sourceMetadata->source);

        } finally {
            if (isset($sourceFile) && is_resource($sourceFile)) {
                fclose($sourceFile);
            }
        }
    }

    public static function filesDataProvider(): \Generator
    {
        yield 'file-with-self.php' => [
            __DIR__ . '/_files/file-with-self.php',
            __DIR__ . '/_files/file-with-self-transformed.php'
        ];
        yield 'file-with-self-no-namespace.php' => [
            __DIR__ . '/_files/file-with-self-no-namespace.php',
            __DIR__ . '/_files/file-with-self-no-namespace-transformed.php'
        ];
        yield 'php80-file.php' => [
            __DIR__ . '/_files/php80-file.php',
            __DIR__ . '/_files/php80-file-transformed.php'
        ];
        yield 'php81-file.php' => [
            __DIR__ . '/_files/php81-file.php',
            __DIR__ . '/_files/php81-file-transformed.php'
        ];
        yield 'php82-file.php' => [
            __DIR__ . '/_files/php82-file.php',
            __DIR__ . '/_files/php82-file-transformed.php'
        ];
        yield 'anonymous-class.php' => [
            __DIR__ . '/_files/anonymous-class.php',
            __DIR__ . '/_files/anonymous-class-transformed.php'
        ];
    }
}
