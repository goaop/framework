<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Vfs\FileSystem;

class WeavingTransformerTest extends TestCase
{
    protected static FileSystem $fileSystem;

    protected WeavingTransformer $transformer;

    protected ?AspectKernel $kernel;

    protected ?AdviceMatcherInterface $adviceMatcher;

    protected ?CachePathManager $cachePathManager;

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        static::$fileSystem = FileSystem::factory('vfs://');
        static::$fileSystem->mount();
    }

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        $container = $this->getContainerMock();
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();

        $this->adviceMatcher = $this->getAdviceMatcherMock();
        $this->kernel        = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'vfs://',
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => []
            ],
            $container
        );
        $this->cachePathManager = $this
            ->getMockBuilder(CachePathManager::class)
            ->setConstructorArgs([$this->kernel])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->adviceMatcher,
            $this->cachePathManager,
            $loader
        );
    }

    /**
     * It's a caution check that multiple namespaces are not yet supported
     */
    public function testMultipleNamespacesInOneFile(): void
    {
        $metadata = $this->loadTestMetadata('multiple-ns');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('multiple-ns-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for code without classes
     */
    public function testEmptyNamespaceInFile(): void
    {
        $metadata = $this->loadTestMetadata('empty-classes');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('empty-classes')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Do not make anything for aspect class
     */
    public function testAspectIsSkipped(): void
    {
        $metadata = $this->loadTestMetadata('aspect');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('aspect')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Main test case for method with typehint
     */
    public function testWeaverForTypeHint(): void
    {
        $metadata = $this->loadTestMetadata('class-typehint');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('class-typehint-woven')->source);
        $this->assertEquals($expected, $actual);

        $proxyContent = file_get_contents($this->cachePathManager->getCacheDir() . '_proxies/Transformer/_files/class-typehint.php/TestClassTypehint.php');
        $this->assertFalse(strpos($proxyContent, '\\\\Exception'));
    }

    /**
     * Check that weaver can work with PHP7 classes
     */
    public function testWeaverForPhp7Class(): void
    {
        $metadata = $this->loadTestMetadata('php7-class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php7-class-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php7-class-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * Transformer verifies include paths
     */
    public function testTransformerWithIncludePaths(): void
    {
        $container = $this->getContainerMock();
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();

        $kernel = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'vfs://',
                'includePaths'  => [__DIR__],
                'excludePaths'  => [],
                'cacheFileMode' => 0770,
            ],
            $container
        );
        $cachePathManager = $this->getMockBuilder(CachePathManager::class)
            ->setConstructorArgs([$kernel])
            ->enableProxyingToOriginalMethods()
            ->getMock();

        $this->transformer = new WeavingTransformer(
            $kernel,
            $this->adviceMatcher,
            $cachePathManager,
            $loader
        );

        $metadata = $this->loadTestMetadata('class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('class-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('class-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * Testcase for multiple classes (@see https://github.com/lisachenko/go-aop-php/issues/71)
     */
    public function testMultipleClasses(): void
    {
        $metadata = $this->loadTestMetadata('multiple-classes');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('multiple-classes-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Normalizes string context
     */
    protected function normalizeWhitespaces(string $value): string
    {
        return strtr(
            preg_replace('/\s+$/m', '', $value),
            [
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            ]
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @param array           $options   Additional options for kernel
     * @param AspectContainer $container Container instance
     *
     * @return MockObject|AspectKernel
     */
    protected function getKernelMock(array $options, AspectContainer $container): AspectKernel
    {
        $mock = $this->getMockForAbstractClass(
            AspectKernel::class,
            [],
            '',
            false,
            true,
            true,
            ['getOptions', 'getContainer', 'hasFeature']
        );

        $mock->method('getOptions')
            ->willReturn($options);

        $mock->method('getContainer')
            ->willReturn($container);

        return $mock;
    }

    /**
     * Returns a mock for advice matcher
     *
     * @return MockObject|AdviceMatcherInterface
     */
    protected function getAdviceMatcherMock(): AdviceMatcherInterface
    {
        $mock = $this->createMock(AdviceMatcherInterface::class);
        $mock
            ->method('getAdvicesForClass')
            ->will(
                $this->returnCallback(function (ReflectionClass $refClass) {
                    $advices  = [];
                    foreach ($refClass->getMethods() as $method) {
                        $advisorId = "advisor.{$refClass->name}->{$method->name}";
                        $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = true;
                    }
                    return $advices;
                })
            );

        return $mock;
    }

    /**
     * @param string $name Name of the file to load
     */
    private function loadTestMetadata(string $name): StreamMetaData
    {
        $fileName = __DIR__ . '/_files/' . $name . '.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        $source   = file_get_contents($fileName);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    /**
     * Returns a mock for the container
     *
     * @return AspectContainer|MockObject
     */
    private function getContainerMock(): AspectContainer
    {
        $container = $this->createMock(AspectContainer::class);

        $container
            ->method('getServicesByInterface')
            ->willReturnMap([
                [Advisor::class, []]
            ]);

        return $container;
    }
}
