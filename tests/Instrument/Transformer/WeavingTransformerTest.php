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

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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
        $this->cachePathManager = new CachePathManager($this->kernel);

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
        $cachePathManager = new CachePathManager($kernel);

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
     * Regression test: final readonly class must be proxied without a parse error.
     *
     * WeavingTransformer::convertClassToTrait() must strip T_FINAL, T_ABSTRACT, and T_READONLY
     * before the class keyword because PHP traits do not support these modifiers.
     * The proxy class is intentionally non-readonly.
     */
    public function testWeaverForFinalReadonlyClass(): void
    {
        $metadata = $this->loadTestMetadata('final-readonly-class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('final-readonly-class-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('final-readonly-class-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * PHP 8.1 backed enums must be woven: methods go into a trait, cases are re-declared in the proxy enum.
     */
    public function testWeaverForEnum(): void
    {
        $metadata = $this->loadTestMetadata('php81-enum');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php81-enum-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php81-enum-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * Enum case declarations are removed from the woven trait, but the blank lines they occupied
     * must be preserved so that subsequent method declarations remain on the same line numbers as
     * in the original source file. This is required for XDebug breakpoints to map correctly.
     */
    public function testWeaverForEnumPreservesMethodLineNumbers(): void
    {
        $originalSource  = $this->loadTestMetadata('php81-enum')->source;
        $originalLines   = explode("\n", $originalSource);
        $labelLineInOrig = null;
        foreach ($originalLines as $i => $line) {
            if (preg_match('/public function label\s*\(/', $line)) {
                $labelLineInOrig = $i + 1; // 1-based
                break;
            }
        }
        $this->assertNotNull($labelLineInOrig, 'label() not found in original source');

        $metadata = $this->loadTestMetadata('php81-enum');
        $this->transformer->transform($metadata);

        $wovenLines    = explode("\n", $metadata->source);
        $labelLineWoven = null;
        foreach ($wovenLines as $i => $line) {
            if (preg_match('/public function label\s*\(/', $line)) {
                $labelLineWoven = $i + 1;
                break;
            }
        }

        $this->assertSame(
            $labelLineInOrig,
            $labelLineWoven,
            'label() must appear at the same line number in the woven trait as in the original enum source'
        );
    }

    /**
     * PHP 8.3 #[\Override] attribute must be stripped from intercepted methods.
     *
     * When a method is aliased in the proxy's trait-use block (e.g. __aop__overriddenMethod),
     * PHP copies attributes to the alias. Since __aop__overriddenMethod has no matching parent
     * method, #[\Override] would cause a fatal error — so WeavingTransformer must remove it.
     */
    public function testWeaverStripsOverrideAttributeFromInterceptedMethods(): void
    {
        $metadata = $this->loadTestMetadata('php83-override');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php83-override-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php83-override-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * PHP 8.3 #[\Override] combined with other attributes in the same group: only #[\Override]
     * must be stripped from the woven trait — the other attributes must be preserved.
     */
    public function testWeaverStripsOnlyOverrideFromMultiAttributeGroup(): void
    {
        $metadata = $this->loadTestMetadata('php83-override-multiattr');
        $this->transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        // #[\Override] must be gone from the trait body (alone or as part of a group)
        $this->assertStringNotContainsString('#[\Override]', $actual);
        $this->assertStringNotContainsString('#[\Override,', $actual);
        $this->assertStringNotContainsString(', \Override]', $actual);

        // The non-Override companion attribute must survive in the woven trait
        $this->assertStringContainsString('#[\FakeAttr]', $actual);
    }

    public function testWeaverMovesInterceptedPropertiesToProxyHooks(): void
    {
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher
            ->method('getAdvicesForClass')
            ->willReturn([
                AspectContainer::PROPERTY_PREFIX => [
                    'value' => ['advisor.Go\Tests\TestProject\Application\Php84PropertyHooksClass->value' => true],
                    'limited' => ['advisor.Go\Tests\TestProject\Application\Php84PropertyHooksClass->limited' => true],
                ],
            ]);
        $adviceMatcher
            ->method('getAdvicesForFunctions')
            ->willReturn([]);

        $loader = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$this->getContainerMock()])
            ->getMock();
        $transformer = new WeavingTransformer(
            $this->kernel,
            $adviceMatcher,
            $this->cachePathManager,
            $loader
        );

        $metadata = $this->loadTestMetadata('php84-property-hooks');
        $transformer->transform($metadata);

        $actualWoven = $this->normalizeWhitespaces($metadata->source);
        $this->assertStringContainsString(
            "// public string \$value = 'test'; // Moved by weaving interceptor to the {@see Go\\Tests\\TestProject\\Application\\Php84PropertyHooksClass->value}",
            $actualWoven
        );
        $this->assertStringContainsString(
            "// public protected(set) string \$limited = 'limited'; // Moved by weaving interceptor to the {@see Go\\Tests\\TestProject\\Application\\Php84PropertyHooksClass->limited}",
            $actualWoven
        );
        $this->assertStringContainsString("public string \$plain = 'plain';", $actualWoven);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actualWoven, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));

        $this->assertStringContainsString("public string \$value = 'test' {", $proxyContent);
        $this->assertStringContainsString("public protected(set) string \$limited = 'limited' {", $proxyContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(self::class, 'value'", $proxyContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(self::class, 'limited'", $proxyContent);
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
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer', 'hasFeature'])
            ->getMock();

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
            ->willReturnCallback(function (ReflectionClass $refClass) {
                $advices  = [];
                foreach ($refClass->getMethods() as $method) {
                    $advisorId = "advisor.{$refClass->name}->{$method->name}";
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = true;
                }
                return $advices;
            });

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
