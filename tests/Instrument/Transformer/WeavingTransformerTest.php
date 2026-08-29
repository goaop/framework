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
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

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
        static::$fileSystem = FileSystem::mount('vfs');
    }

    public static function tearDownAfterClass(): void
    {
        static::$fileSystem->unmount();
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

        $proxyContent = file_get_contents($this->cachePathManager->getCacheDir() . '/Transformer/_files/class-typehint.php');
        $this->assertNotFalse($proxyContent);
        $this->assertStringNotContainsString('\\\\Exception', $proxyContent);
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
     * Backed enum cases whose values are constant expressions (issue #600).
     *
     * `case Negative = -1;`, `case Shifted = 1 << 2;` and `case FromConst = self::SHIFT + 10;`
     * are not String_/Int_ literals in the AST. The proxy enum must re-declare these cases with
     * their original expressions verbatim — dropping the value would declare a pure case inside
     * a backed enum, which is a PHP fatal error.
     */
    public function testWeaverForEnumWithConstantExpressionCaseValues(): void
    {
        $metadata = $this->loadTestMetadata('php81-enum-const-expr');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php81-enum-const-expr-woven')->source);
        $this->assertEquals($expected, $actual);
        $this->assertMatchesRegularExpression("/AOP_CACHE_DIR . '(.+)';$/m", $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces(file_get_contents('vfs://' . $matches[1]));
            $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php81-enum-const-expr-proxy')->source);
            $this->assertEquals($expectedProxyContent, $actualProxyContent);
        }
    }

    /**
     * Functional check for issue #600: the woven trait plus the generated proxy enum must
     * actually load and keep the evaluated constant-expression case values at runtime.
     *
     * This also proves that `self::SHIFT` keeps resolving on the proxy enum: the class constant
     * stays in the woven trait, and trait constants participate in the composing class (PHP 8.2+).
     */
    public function testWovenEnumWithConstantExpressionCaseValuesWorksAtRuntime(): void
    {
        $metadata = $this->loadTestMetadata('php81-enum-const-expr');
        $this->transformer->transform($metadata);

        $this->assertMatchesRegularExpression("/AOP_CACHE_DIR . '(.+)';$/m", $metadata->source);
        preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $metadata->source, $matches);
        $proxyContent = file_get_contents('vfs://' . $matches[1]);

        // The woven trait source, without the include_once tail (the proxy is included manually)
        $traitSource = preg_replace('/^include_once AOP_CACHE_DIR.*$/m', '', $metadata->source);

        $tempDir   = sys_get_temp_dir();
        $traitFile = tempnam($tempDir, 'aop_enum_trait_');
        $proxyFile = tempnam($tempDir, 'aop_enum_proxy_');
        try {
            file_put_contents($traitFile, $traitSource);
            file_put_contents($proxyFile, $proxyContent);
            include $traitFile;
            include $proxyFile;

            $enumName = 'Test\\ns1\\ConstExprStatus';
            $this->assertTrue(enum_exists($enumName));
            $this->assertSame(-1, $enumName::Negative->value);
            $this->assertSame(1 << 2, $enumName::Shifted->value);
            $this->assertSame(12, $enumName::FromConst->value, 'self::SHIFT + 10 must resolve via the trait constant');
            $this->assertSame($enumName::FromConst, $enumName::from(12));
        } finally {
            unlink($traitFile);
            unlink($proxyFile);
        }
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

    /**
     * PHP 8.0/8.1 non-scalar and non-foldable attribute arguments must be copied to the
     * proxy from the AST verbatim: enum cases, new-in-initializer objects and global
     * constants must never be evaluated at weave time.
     *
     * @see https://github.com/goaop/framework/issues/601
     * @see https://github.com/goaop/framework/issues/602
     */
    public function testWeaverCopiesNonScalarAttributeArgumentsFromAst(): void
    {
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher
            ->method('getAdvicesForClass')
            ->willReturnCallback(function (ReflectionClass $refClass) {
                // Only weave the target class — leave the enum and the attribute class alone
                if ($refClass->getShortName() !== 'TestAttributeArgsClass') {
                    return [];
                }
                $advices = [];
                foreach ($refClass->getMethods() as $method) {
                    $advisorId = "advisor.{$refClass->name}->{$method->name}";
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = true;
                }
                return $advices;
            });
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

        $metadata = $this->loadTestMetadata('php81-attr-args');
        $transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php81-attr-args-woven')->source);
        $this->assertEquals($expected, $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php81-attr-args-proxy')->source);
        $this->assertEquals($expectedProxyContent, $actualProxyContent);

        // The argument expressions must be preserved, not evaluated/constant-folded
        $this->assertStringContainsString('\Test\ns1\AttrStatus::Active', $actualProxyContent);
        $this->assertStringContainsString('new \ArrayObject([1, 2])', $actualProxyContent);
        $this->assertStringContainsString('PHP_INT_MAX', $actualProxyContent);
        $this->assertStringNotContainsString((string) PHP_INT_MAX, $actualProxyContent);
    }

    /**
     * Class-level attributes (with and without arguments) must survive the class→trait
     * conversion untouched (issue #598). Previously the first token inside `#[...]` was
     * renamed to the trait name and the rest of the attribute plus the real class header
     * was deleted, producing a parse error like `#[Foo__AopProxied {`.
     */
    public function testWeaverKeepsClassLevelAttributesOnWovenTrait(): void
    {
        $metadata = $this->loadTestMetadata('php80-class-attribute');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-class-attribute-woven')->source);
        $this->assertEquals($expected, $actual);
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
     * Intercepted promoted constructor properties must be demoted to plain parameters in the
     * woven trait — keeping type and default value — with explicit assignments injected at the
     * start of the constructor body (issue #599). The proxy re-declares the property with
     * interception hooks and must keep the original default value.
     */
    public function testWeaverDemotesInterceptedPromotedProperties(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'name' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name' => true],
                'counter' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter' => true],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->__construct' => true],
                'getName' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->getName' => true],
            ],
        ]);

        $metadata = $this->loadTestMetadata('php80-promoted-property');
        $transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-promoted-property-woven')->source);
        $this->assertEquals($expected, $actual);

        // Non-intercepted promoted parameter must stay promoted in the trait
        $this->assertStringContainsString('protected ?\ArrayObject $bag = null', $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php80-promoted-property-proxy')->source);
        $this->assertEquals($expectedProxyContent, $actualProxyContent);

        // The proxy hook property must keep the original promoted default value
        $this->assertStringContainsString("private string \$name = 'initial' {", $actualProxyContent);
    }

    /**
     * A promoted property inside a single-line constructor must weave without a parse error
     * (issue #599). Commenting the parameter out used to swallow the closing ')' and '{'.
     */
    public function testWeaverDemotesPromotedPropertyInSingleLineConstructor(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'tag' => ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag' => true],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->__construct' => true],
            ],
        ]);

        $metadata = $this->loadTestMetadata('php80-promoted-property-single-line');
        $transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-promoted-property-single-line-woven')->source);
        $this->assertEquals($expected, $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php80-promoted-property-single-line-proxy')->source);
        $this->assertEquals($expectedProxyContent, $actualProxyContent);
    }

    /**
     * PHP 8.5 `final` promoted constructor properties must demote cleanly: the woven trait
     * drops both `final` and the visibility modifier, while the proxy re-declares the
     * property as final with the original default value (issue #599).
     */
    #[\PHPUnit\Framework\Attributes\RequiresPhp('>= 8.5.0')]
    public function testWeaverDemotesFinalPromotedProperty(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'token' => ['advisor.Go\Instrument\Transformer\Stubs\FinalPromotedClass85->token' => true],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Instrument\Transformer\Stubs\FinalPromotedClass85->__construct' => true],
            ],
        ]);

        $fileName = __DIR__ . '/Stubs/FinalPromotedClass85.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        $metadata = new StreamMetaData($stream, (string) file_get_contents($fileName));
        fclose($stream);
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);
        $this->assertStringContainsString(
            "public function __construct(string \$token = 'secret') { \$this->token = \$token;}",
            $actual
        );

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $this->assertStringContainsString("final public string \$token = 'secret' {", $proxyContent);
    }

    /**
     * Creates a WeavingTransformer whose advice matcher returns the given advices for any class.
     */
    private function createTransformerWithAdvices(array $advices): WeavingTransformer
    {
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher->method('getAdvicesForClass')->willReturn($advices);
        $adviceMatcher->method('getAdvicesForFunctions')->willReturn([]);

        $loader = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$this->getContainerMock()])
            ->getMock();

        return new WeavingTransformer(
            $this->kernel,
            $adviceMatcher,
            $this->cachePathManager,
            $loader
        );
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
