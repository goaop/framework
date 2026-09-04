<?php

declare(strict_types=1);
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
use Go\Aop\Framework\BeforeInterceptor;
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

    protected AspectKernel $kernel;

    protected AdviceMatcherInterface $adviceMatcher;

    protected CachePathManager $cachePathManager;

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
                'excludePaths'  => [],
            ],
            $container,
        );
        $this->cachePathManager = new CachePathManager($this->kernel);

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->adviceMatcher,
            $this->cachePathManager,
            $loader,
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
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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
            $container,
        );
        $cachePathManager = new CachePathManager($kernel);

        $this->transformer = new WeavingTransformer(
            $kernel,
            $this->adviceMatcher,
            $cachePathManager,
            $loader,
        );

        $metadata = $this->loadTestMetadata('class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('class-woven')->source);
        $this->assertEquals($expected, $actual);
        if (preg_match("/AOP_CACHE_DIR . '(.+)';$/", $actual, $matches)) {
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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
            'label() must appear at the same line number in the woven trait as in the original enum source',
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
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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

        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $metadata->source, $matches));
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

            // The enum only exists after the runtime includes above, so inspect it via cases()
            $enumName = 'Test\\ns1\\ConstExprStatus';
            $this->assertTrue(enum_exists($enumName));
            $cases = $enumName::cases();
            $this->assertIsArray($cases);
            $caseValues = [];
            foreach ($cases as $case) {
                $this->assertInstanceOf(\BackedEnum::class, $case);
                $caseValues[$case->name] = $case->value;
            }
            $this->assertSame(-1, $caseValues['Negative'] ?? null);
            $this->assertSame(1 << 2, $caseValues['Shifted'] ?? null);
            $this->assertSame(12, $caseValues['FromConst'] ?? null, 'self::SHIFT + 10 must resolve via the trait constant');
            $this->assertSame('FromConst', array_search(12, $caseValues, true), 'from(12) must resolve to the FromConst case');
        } finally {
            unlink($traitFile);
            unlink($proxyFile);
        }
    }

    /**
     * PHP 8.3 #[\Override] attribute must be stripped from intercepted methods.
     *
     * When a method is aliased in the proxy's trait-use block (e.g. overriddenMethodOriginal),
     * PHP copies attributes to the alias. Since overriddenMethodOriginal has no matching parent
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
            $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
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
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
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
            $loader,
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
     * was deleted, producing a parse error like `#[FooOriginal {`.
     */
    public function testWeaverKeepsClassLevelAttributesOnWovenTrait(): void
    {
        $metadata = $this->loadTestMetadata('php80-class-attribute');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-class-attribute-woven')->source);
        $this->assertEquals($expected, $actual);
    }

    /**
     * Golden-file coverage of general PHP 8.0-8.3 syntax through the current weaver
     * (issue #610): constructor promotion (non-intercepted property), new-in-initializer
     * parameter default, named arguments, match expression, nullsafe operator, enum usage
     * in a method body, readonly property, first-class callable and a typed class constant.
     * Only the class is woven — the enum in the same file must stay untouched.
     */
    public function testWeaverForPhp80To82Syntax(): void
    {
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher
            ->method('getAdvicesForClass')
            ->willReturnCallback(function (ReflectionClass $refClass) {
                // Weave only the target class — the enum stays untouched
                if ($refClass->getShortName() !== 'TestPhp80To82SyntaxClass') {
                    return [];
                }
                $advices = [];
                foreach ($refClass->getMethods() as $method) {
                    $advisorId = "advisor.{$refClass->name}->{$method->name}";
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
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
            $loader,
        );

        $metadata = $this->loadTestMetadata('php80-82-syntax');
        $transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-82-syntax-woven')->source);
        $this->assertEquals($expected, $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $actualProxyContent   = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $expectedProxyContent = $this->normalizeWhitespaces($this->loadTestMetadata('php80-82-syntax-proxy')->source);
        $this->assertEquals($expectedProxyContent, $actualProxyContent);
    }

    /**
     * Attribute classes must be weavable (issue #615): #[\Attribute] and
     * #[\AllowDynamicProperties] are compile-time invalid on traits, so they must be removed
     * from the woven trait tokens. In a grouped attribute only the incompatible entry is
     * removed. The proxy class must keep the original attribute groups (copied from the AST).
     */
    public function testWeaverStripsAttributeClassMarkersFromWovenTrait(): void
    {
        $metadata = $this->loadTestMetadata('php80-attribute-class');
        $this->transformer->transform($metadata);

        $actual   = $this->normalizeWhitespaces($metadata->source);
        $expected = $this->normalizeWhitespaces($this->loadTestMetadata('php80-attribute-class-woven')->source);
        $this->assertEquals($expected, $actual);

        // Incompatible attributes must be gone from every woven trait
        $this->assertStringNotContainsString('#[\Attribute', $actual);
        $this->assertStringNotContainsString('\AllowDynamicProperties', $actual);
        // The compatible part of the grouped attribute must survive
        $this->assertStringContainsString('#[\FakeMarkerAttr]', $actual);

        // The proxy (last class in the file wins the shared cache path) must keep #[\Attribute(...)]
        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = (string) file_get_contents('vfs://' . $matches[1]);
        $this->assertStringContainsString(
            '#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]',
            $proxyContent,
        );
    }

    public function testWeaverMovesInterceptedPropertiesToProxyHooks(): void
    {
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher
            ->method('getAdvicesForClass')
            ->willReturn([
                AspectContainer::PROPERTY_PREFIX => [
                    'value' => [
                        'advisor.Go\Tests\TestProject\Application\Php84PropertyHooksClass->value' => new BeforeInterceptor(static function (): void {}),
                    ],
                    'limited' => [
                        'advisor.Go\Tests\TestProject\Application\Php84PropertyHooksClass->limited' => new BeforeInterceptor(static function (): void {}),
                    ],
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
            $loader,
        );

        $metadata = $this->loadTestMetadata('php84-property-hooks');
        $transformer->transform($metadata);

        $actualWoven = $this->normalizeWhitespaces($metadata->source);
        $this->assertStringContainsString(
            "// public string \$value = 'test'; // Moved by weaving interceptor to the {@see Go\\Tests\\TestProject\\Application\\Php84PropertyHooksClass->value}",
            $actualWoven,
        );
        $this->assertStringContainsString(
            "// public protected(set) string \$limited = 'limited'; // Moved by weaving interceptor to the {@see Go\\Tests\\TestProject\\Application\\Php84PropertyHooksClass->limited}",
            $actualWoven,
        );
        $this->assertStringContainsString("public string \$plain = 'plain';", $actualWoven);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actualWoven, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));

        $this->assertStringContainsString("public string \$value = 'test' {", $proxyContent);
        $this->assertStringContainsString("public protected(set) string \$limited = 'limited' {", $proxyContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyContent);
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
                'name' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name' => new BeforeInterceptor(static function (): void {})],
                'counter' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter' => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->__construct' => new BeforeInterceptor(static function (): void {})],
                'getName' => ['advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->getName' => new BeforeInterceptor(static function (): void {})],
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
     * An intercepted promoted property whose default is a new-in-initializer expression
     * must not carry the default onto the proxy hook property (issue #616): `new` is legal
     * in a parameter default but illegal in a property initializer. The property stays
     * uninitialized in the proxy — the constructor assignment injected by the demotion
     * supplies the value, and the isInitialized() guard covers the pre-construction window.
     */
    public function testWeaverSkipsNewInInitializerDefaultOnProxyHookProperty(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'bag' => ['advisor.Go\Tests\TestProject\Application\NewInInitializerClass->bag' => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Tests\TestProject\Application\NewInInitializerClass->__construct' => new BeforeInterceptor(static function (): void {})],
                'getBagItems' => ['advisor.Go\Tests\TestProject\Application\NewInInitializerClass->getBagItems' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $metadata = $this->loadTestMetadata('php81-new-in-initializer');
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        // Demoted parameter keeps its new-in-initializer default in the woven trait...
        $this->assertStringContainsString("\ArrayObject \$bag = new \ArrayObject(['seed'])", $actual);
        // ...and the injected constructor assignment routes the value through the proxy set hook
        $this->assertStringContainsString('$this->bag = $bag;', $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = (string) file_get_contents('vfs://' . $matches[1]);

        // The hook property must NOT carry the new-in-initializer default (compile error);
        // note the proxy __construct parameter legitimately keeps it (legal in param defaults)
        $this->assertStringNotContainsString('private \ArrayObject $bag =', $proxyContent);
        $this->assertStringContainsString('private \ArrayObject $bag {', $proxyContent);
        // Uninitialized typed property must be guarded in the hooks
        $this->assertStringContainsString('isInitialized($this)', $proxyContent);

        // The generated proxy must stay parseable as PHP (guards against emitting
        // constructs that are syntactically invalid in property context)
        $parser = (new \PhpParser\ParserFactory())->createForHostVersion();
        $this->assertNotNull($parser->parse($proxyContent));
    }

    /**
     * A promoted property inside a single-line constructor must weave without a parse error
     * (issue #599). Commenting the parameter out used to swallow the closing ')' and '{'.
     */
    public function testWeaverDemotesPromotedPropertyInSingleLineConstructor(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'tag' => ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag' => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->__construct' => new BeforeInterceptor(static function (): void {})],
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
                'token' => ['advisor.Go\Instrument\Transformer\Stubs\FinalPromotedClass85->token' => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ['advisor.Go\Instrument\Transformer\Stubs\FinalPromotedClass85->__construct' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $fileName = __DIR__ . '/Stubs/FinalPromotedClass85.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, (string) file_get_contents($fileName));
        fclose($stream);
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);
        $this->assertStringContainsString(
            "public function __construct(string \$token = 'secret') { \$this->token = \$token;}",
            $actual,
        );

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $this->assertStringContainsString("final public string \$token = 'secret' {", $proxyContent);
    }

    /**
     * Traits keep the legacy weaving strategy: the original trait is renamed to
     * <Name>Original in place (adjustOriginalTrait) and TraitProxyGenerator emits a child
     * trait with the original name. Intercepted properties are commented out of the original
     * trait body — the child trait re-declares them with interception hooks.
     */
    public function testWeaverForTraitRenamesOriginalTraitAndMovesInterceptedProperties(): void
    {
        $classFqn    = Stubs\WeavingTraitStub::class;
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'interceptedProperty' => ["advisor.{$classFqn}->interceptedProperty" => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                'traitMethod' => ["advisor.{$classFqn}->traitMethod" => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::STATIC_METHOD_PREFIX => [
                'traitStaticMethod' => ["advisor.{$classFqn}->traitStaticMethod" => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $metadata = $this->loadStubMetadata('WeavingTraitStub');
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        // The trait keyword stays a trait, only the name gets the Original suffix
        $this->assertStringContainsString('trait WeavingTraitStubOriginal', $actual);
        $this->assertStringNotContainsString('trait WeavingTraitStub' . PHP_EOL, $actual);

        // Intercepted property is commented out, the untouched one survives verbatim
        $this->assertStringContainsString(
            "// public string \$interceptedProperty = 'initial'; // Moved by weaving interceptor to the {@see {$classFqn}->interceptedProperty}",
            $actual,
        );
        $this->assertStringContainsString('protected int $plainProperty = 0;', $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));

        // The generated child trait keeps the original short name and uses the renamed trait
        $this->assertStringContainsString('trait WeavingTraitStub', $proxyContent);
        $this->assertStringContainsString('WeavingTraitStubOriginal', $proxyContent);
        $this->assertStringContainsString('traitMethodOriginal', $proxyContent);
        $this->assertStringContainsString('traitStaticMethodOriginal', $proxyContent);
        $this->assertStringContainsString('$interceptedProperty', $proxyContent);
    }

    /**
     * `abstract` is a class-only modifier — convertClassToTrait() must drop it, otherwise the
     * woven file would contain the invalid `abstract trait ...` declaration. Abstract *methods*
     * remain legal inside the trait and must be kept.
     */
    public function testWeaverStripsAbstractModifierFromWovenTrait(): void
    {
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::METHOD_PREFIX => [
                'concreteMethod' => ['advisor.Test\ns1\TestAbstractClass->concreteMethod' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $metadata = $this->loadTestMetadata('abstract-class');
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        $this->assertStringContainsString('trait TestAbstractClassOriginal', $actual);
        $this->assertStringNotContainsString('abstract trait', $actual);
        $this->assertStringNotContainsString('abstract class', $actual);
        // Abstract methods are legal in traits and must survive untouched
        $this->assertStringContainsString('abstract public function abstractMethod(): string;', $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));
        $this->assertStringContainsString('abstract class TestAbstractClass', $proxyContent);
    }

    /**
     * When the trait-incompatible marker is the last entry of a grouped attribute
     * (`#[\FakeMarkerAttr, \Attribute]`) there is no trailing comma to remove, so the
     * *leading* comma has to be dropped instead — otherwise the woven trait would carry
     * the syntactically invalid `#[\FakeMarkerAttr, ]`.
     */
    public function testWeaverRemovesLeadingCommaWhenIncompatibleAttributeIsLastInGroup(): void
    {
        $metadata = $this->loadTestMetadata('php80-attribute-class-last');
        $this->transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        $this->assertStringContainsString('#[\FakeMarkerAttr]', $actual);
        $this->assertStringNotContainsString('\Attribute', $actual);
        $this->assertStringNotContainsString('#[\FakeMarkerAttr,', $actual);
        $this->assertStringContainsString('trait TestLastGroupedAttributeClassOriginal', $actual);

        // The woven trait must still be parseable PHP after the comma surgery
        $parser = (new \PhpParser\ParserFactory())->createForHostVersion();
        $traitSource = preg_replace('/^include_once AOP_CACHE_DIR.*$/m', '', $metadata->source);
        $this->assertNotNull($parser->parse((string) $traitSource));

        // ...and the proxy class keeps the original attribute group untouched
        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = (string) file_get_contents('vfs://' . $matches[1]);
        $this->assertStringContainsString('#[\Attribute]', $proxyContent);
    }

    /**
     * Attribute groups around a promoted constructor property must be skipped as a whole while
     * the property is demoted to a plain parameter (issue #599): their arguments may contain
     * arbitrary nested brackets, and the attributes themselves have to stay on the parameter.
     * The same applies to the attribute group in front of the `function` keyword when the
     * injected assignment position (constructor body brace) is located.
     */
    public function testWeaverDemotesPromotedPropertyWithAttributes(): void
    {
        $classFqn    = Stubs\AttributedPromotedClass::class;
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'name' => ["advisor.{$classFqn}->name" => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                '__construct' => ["advisor.{$classFqn}->__construct" => new BeforeInterceptor(static function (): void {})],
                'getName' => ["advisor.{$classFqn}->getName" => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $metadata = $this->loadStubMetadata('AttributedPromotedClass');
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        // Both attribute groups survive verbatim...
        $this->assertStringContainsString('#[MarkerAttribute([1, 2])]', $actual);
        $this->assertStringContainsString("#[MarkerAttribute(['a' => ['b']])]", $actual);
        // ...while the promotion modifier is gone and the parameter keeps type + default
        $this->assertStringNotContainsString("private string \$name = 'initial'", $actual);
        $this->assertStringContainsString("string \$name = 'initial'", $actual);
        // The assignment is injected right after the constructor body brace
        $this->assertStringContainsString('$this->name = $name;', $actual);

        $parser = (new \PhpParser\ParserFactory())->createForHostVersion();
        $traitSource = preg_replace('/^include_once AOP_CACHE_DIR.*$/m', '', $metadata->source);
        $this->assertNotNull($parser->parse((string) $traitSource));

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = (string) file_get_contents('vfs://' . $matches[1]);
        $this->assertStringContainsString("private string \$name = 'initial' {", $proxyContent);
        $this->assertNotNull($parser->parse($proxyContent));
    }

    /**
     * Advices matching members that the woven class only inherits must not be looked up in the
     * woven trait tokens — those declarations live in the parent file, not in the converted
     * class body. The proxy dispatches the inherited method through the `parent::method(...)`
     * first-class callable instead of a trait alias.
     */
    public function testWeaverInterceptsInheritedMembersWithoutTouchingTraitBody(): void
    {
        $classFqn    = Stubs\InheritedMethodChild::class;
        $transformer = $this->createTransformerWithAdvices([
            AspectContainer::PROPERTY_PREFIX => [
                'inheritedProperty' => ["advisor.{$classFqn}->inheritedProperty" => new BeforeInterceptor(static function (): void {})],
            ],
            AspectContainer::METHOD_PREFIX => [
                'inheritedMethod' => ["advisor.{$classFqn}->inheritedMethod" => new BeforeInterceptor(static function (): void {})],
                'ownMethod' => ["advisor.{$classFqn}->ownMethod" => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $metadata = $this->loadStubMetadata('InheritedMethodChild');
        $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);

        $this->assertStringContainsString('trait InheritedMethodChildOriginal', $actual);
        // The `extends` clause is moved from the trait to the proxy
        $this->assertStringNotContainsString('extends InheritedMethodBase', $actual);
        // Nothing of the parent declarations may be commented out in the child's trait body
        $this->assertStringNotContainsString('Moved by weaving interceptor', $actual);

        $matches = [];
        $this->assertSame(1, preg_match("/AOP_CACHE_DIR . '(.+)';$/m", $actual, $matches));
        $proxyContent = $this->normalizeWhitespaces((string) file_get_contents('vfs://' . $matches[1]));

        $this->assertStringContainsString(
            'class InheritedMethodChild extends \Go\Instrument\Transformer\Stubs\InheritedMethodBase',
            $proxyContent,
        );
        // Own method is aliased in the trait-use block, inherited one goes through parent::
        $this->assertStringContainsString('as private ownMethodOriginal;', $proxyContent);
        $this->assertStringNotContainsString('inheritedMethodOriginal', $proxyContent);
        $this->assertStringContainsString('parent::inheritedMethod(...)', $proxyContent);
    }

    /**
     * Global functions called from a namespace are woven into a per-namespace proxy file placed
     * in the `_functions/` cache sub-directory, and the original file receives an include_once
     * appended to the last token of the namespace.
     *
     * The proxy file itself is only (re)built when it is older than the aspect container — an
     * up-to-date cache file is reused as is, and the include_once is still emitted.
     */
    public function testWeaverIncludesFunctionProxyAndReusesFreshCacheFile(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container
            ->method('getServicesByInterface')
            ->willReturnMap([[Advisor::class, []]]);
        $container
            ->method('isFreshSince')
            ->willReturn(true);

        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher->method('getAdvicesForClass')->willReturn([]);
        $adviceMatcher->method('getAdvicesForFunctions')->willReturn([
            AspectContainer::FUNCTION_PREFIX => [
                'array_product' => ['advisor.functions' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $kernel = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'vfs://',
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => [],
            ],
            $container,
        );
        $loader = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();
        $transformer = new WeavingTransformer(
            $kernel,
            $adviceMatcher,
            new CachePathManager($kernel),
            $loader,
        );

        // Pre-create the cache file so that its mtime is compared against the container
        if (!file_exists('vfs:///_functions/Test')) {
            mkdir('vfs:///_functions/Test', 0770, true);
        }
        file_put_contents('vfs:///_functions/Test/ns1.php', '<?php // already generated');

        $metadata = $this->loadTestMetadata('functions-weaving');
        $result   = $transformer->transform($metadata);

        $this->assertSame(TransformerResultEnum::RESULT_TRANSFORMED, $result);
        $this->assertStringContainsString(
            "include_once AOP_CACHE_DIR . '/_functions/Test/ns1.php';",
            $this->normalizeWhitespaces($metadata->source),
        );
        // The up-to-date file was not regenerated
        $this->assertSame('<?php // already generated', file_get_contents('vfs:///_functions/Test/ns1.php'));
    }

    /**
     * With no cache file yet (or a stale one), processFunctions() must generate and write the
     * function proxy file itself. The cache dir lives on the vfs:// stream wrapper, and PHP core
     * rejects the LOCK_EX flag for any non-"file://" stream - file_put_contents() must skip it
     * there just like saveProxyToCache() already does for the class proxy file.
     */
    public function testWeaverGeneratesFunctionProxyCacheFileOnFirstWeave(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container
            ->method('getServicesByInterface')
            ->willReturnMap([[Advisor::class, []]]);
        $container
            ->method('isFreshSince')
            ->willReturn(false);

        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher->method('getAdvicesForClass')->willReturn([]);
        $adviceMatcher->method('getAdvicesForFunctions')->willReturn([
            AspectContainer::FUNCTION_PREFIX => [
                'array_product' => ['advisor.functions' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);

        $kernel = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'vfs://',
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => [],
            ],
            $container,
        );
        $loader = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();
        $transformer = new WeavingTransformer(
            $kernel,
            $adviceMatcher,
            new CachePathManager($kernel),
            $loader,
        );

        // Other tests in this class share the same vfs:// mount and the same fixture namespace;
        // make sure no stale cache file from a previous test is left over.
        if (file_exists('vfs:///_functions/Test/ns1.php')) {
            unlink('vfs:///_functions/Test/ns1.php');
        }

        $metadata = $this->loadTestMetadata('functions-weaving');
        $result   = $transformer->transform($metadata);

        $this->assertSame(TransformerResultEnum::RESULT_TRANSFORMED, $result);
        $this->assertStringContainsString(
            "include_once AOP_CACHE_DIR . '/_functions/Test/ns1.php';",
            $this->normalizeWhitespaces($metadata->source),
        );
        $this->assertFileExists('vfs:///_functions/Test/ns1.php');
        $this->assertStringContainsString('array_product', (string) file_get_contents('vfs:///_functions/Test/ns1.php'));
    }

    /**
     * Without a cache directory there is nowhere to put the generated proxy, so the class is
     * still converted to a trait but no include_once is appended and no function proxy is written.
     */
    public function testWeaverWithoutCacheDirectoryEmitsNoIncludes(): void
    {
        $container = $this->getContainerMock();
        $kernel    = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => null,
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => [],
            ],
            $container,
        );
        $adviceMatcher = $this->createMock(AdviceMatcherInterface::class);
        $adviceMatcher
            ->method('getAdvicesForClass')
            ->willReturn([
                AspectContainer::METHOD_PREFIX => [
                    'concreteMethod' => ['advisor.Test\ns1\TestAbstractClass->concreteMethod' => new BeforeInterceptor(static function (): void {})],
                ],
            ]);
        $adviceMatcher->method('getAdvicesForFunctions')->willReturn([
            AspectContainer::FUNCTION_PREFIX => [
                'array_sum' => ['advisor.functions' => new BeforeInterceptor(static function (): void {})],
            ],
        ]);
        $loader = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();
        $transformer = new WeavingTransformer(
            $kernel,
            $adviceMatcher,
            new CachePathManager($kernel),
            $loader,
        );

        $metadata = $this->loadTestMetadata('abstract-class');
        $result   = $transformer->transform($metadata);

        $actual = $this->normalizeWhitespaces($metadata->source);
        $this->assertSame(TransformerResultEnum::RESULT_TRANSFORMED, $result);
        $this->assertStringContainsString('trait TestAbstractClassOriginal', $actual);
        $this->assertStringNotContainsString('AOP_CACHE_DIR', $actual);
    }

    /**
     * Creates a WeavingTransformer whose advice matcher returns the given advices for any class.
     */
    /**
     * @param array<string, array<string, array<string, mixed>>> $advices
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
            $loader,
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
        $trimmed = preg_replace('/\s+$/m', '', $value);
        assert($trimmed !== null);

        return strtr(
            $trimmed,
            [
                "\r\n" => PHP_EOL,
                "\n"   => PHP_EOL,
            ],
        );
    }

    /**
     * Returns a mock for kernel
     *
     * @param array<string, mixed> $options Additional options for kernel
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
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
                }
                return $advices;
            });

        return $mock;
    }

    /**
     * Loads an autoloadable weaving stub from tests/Instrument/Transformer/Stubs/
     *
     * Stubs (unlike the _files fixtures) live in the Go\Instrument\Transformer\Stubs namespace,
     * so parser-reflection can locate them — required whenever weaving needs property or
     * parent-class reflection for the woven class.
     *
     * @param string $name Short class name of the stub to load
     */
    private function loadStubMetadata(string $name): StreamMetaData
    {
        $fileName = __DIR__ . '/Stubs/' . $name . '.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        assert($stream !== false);
        $source   = file_get_contents($fileName);
        assert($source !== false);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

    /**
     * @param string $name Name of the file to load
     */
    private function loadTestMetadata(string $name): StreamMetaData
    {
        $fileName = __DIR__ . '/_files/' . $name . '.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        assert($stream !== false);
        $source   = file_get_contents($fileName);
        assert($source !== false);
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
                [Advisor::class, []],
            ]);

        return $container;
    }
}
