<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
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
use Go\PhpUnit\AssertsCompilablePhp;
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Weaves PHP 8.0-8.5 syntax fixtures with every method and property intercepted, and checks that
 * both the woven trait and the generated proxy compile.
 */
#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SyntaxFixturesWeavingTest extends TestCase
{
    use AssertsCompilablePhp;

    private const string FIXTURE_DIR = __DIR__ . '/../../Stubs';

    /** Syntax fixture stubs living in tests/Stubs alongside the general-purpose stubs. */
    private const array FIXTURES = [
        'Collaborator',
        'ConstAttr',
        'ExprAttr',
        'Php80ClassAttrPlain',
        'Php80GlobalConstAttrArg',
        'Php81EnumConstExprCases',
        'Php81NewInInitializers',
        'Php81NonScalarAttributeArgs',
        'Php85CloneWith',
        'Php85ClosuresInConstExpr',
        'Php85ConstAttributes',
        'Php85FinalPromotionAsymStatic',
        'Php85NoDiscard',
        'Php85PipeOperator',
        'RichAttr',
        'Status',
    ];

    protected static FileSystem $fileSystem;

    protected WeavingTransformer $transformer;

    protected ?AspectKernel $kernel;

    protected ?CachePathManager $cachePathManager;

    public static function setUpBeforeClass(): void
    {
        static::$fileSystem = FileSystem::mount('syntaxweavingvfs');
    }

    public static function tearDownAfterClass(): void
    {
        static::$fileSystem->unmount();
    }

    public function setUp(): void
    {
        $container = $this->getContainerMock();
        $loader    = $this
            ->getMockBuilder(AspectLoader::class)
            ->setConstructorArgs([$container])
            ->getMock();

        $this->kernel = $this->getKernelMock(
            [
                'appDir'        => dirname(__DIR__),
                'cacheDir'      => 'syntaxweavingvfs://',
                'cacheFileMode' => 0770,
                'includePaths'  => [],
                'excludePaths'  => [],
            ],
            $container,
        );
        $this->cachePathManager = new CachePathManager($this->kernel);

        $this->transformer = new WeavingTransformer(
            $this->kernel,
            $this->getInterceptEverythingMatcher(),
            $this->cachePathManager,
            $loader,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureNames(): array
    {
        $names = [];
        foreach (self::FIXTURES as $name) {
            $names[$name] = [$name];
        }

        return $names;
    }

    #[DataProvider('fixtureNames')]
    public function testWovenTraitAndProxyCompile(string $name): void
    {
        // 8.5-only syntax cannot compile (nor natively reflect) on older runtimes
        if (str_starts_with($name, 'Php85') && PHP_VERSION_ID < 80500) {
            $this->markTestSkipped('Fixture uses PHP 8.5 syntax');
        }

        $metadata = $this->loadFixtureMetadata($name);
        $this->transformer->transform($metadata);

        $woven = (string) $metadata->source;
        $this->assertPhpCompiles($woven, "$name woven trait");

        $this->assertGreaterThan(0, preg_match_all("/AOP_CACHE_DIR . '(.+)';$/m", $woven, $matches));
        foreach ($matches[1] as $proxyPath) {
            $proxy = (string) file_get_contents('syntaxweavingvfs://' . $proxyPath);
            $this->assertPhpCompiles($proxy, "$name proxy $proxyPath");
        }
    }

    private function getInterceptEverythingMatcher(): AdviceMatcherInterface
    {
        $mock = $this->createMock(AdviceMatcherInterface::class);
        $mock
            ->method('getAdvicesForClass')
            ->willReturnCallback(function (ReflectionClass $refClass) {
                $advices = [];
                foreach ($refClass->getMethods() as $method) {
                    if ($method->getDeclaringClass()->name !== $refClass->name) {
                        continue;
                    }
                    $advisorId = "advisor.{$refClass->name}->{$method->name}";
                    $advices[AspectContainer::METHOD_PREFIX][$method->name][$advisorId] = new BeforeInterceptor(static function (): void {});
                }
                foreach ($refClass->getProperties() as $property) {
                    if ($property->getDeclaringClass()->name !== $refClass->name) {
                        continue;
                    }
                    // Mirror the real AdviceMatcher gates (static/readonly/hooked are not interceptable)
                    if ($property->isStatic() || $property->isReadOnly() || $property->hasHooks()) {
                        continue;
                    }
                    $advisorId = "advisor.{$refClass->name}->{$property->name}";
                    $advices[AspectContainer::PROPERTY_PREFIX][$property->name][$advisorId] = new BeforeInterceptor(static function (): void {});
                }
                return $advices;
            });
        $mock->method('getAdvicesForFunctions')->willReturn([]);

        return $mock;
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function getKernelMock(array $options, AspectContainer $container): AspectKernel
    {
        $mock = $this->getMockBuilder(AspectKernel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['configureAop', 'getOptions', 'getContainer', 'hasFeature'])
            ->getMock();

        $mock->method('getOptions')->willReturn($options);
        $mock->method('getContainer')->willReturn($container);

        return $mock;
    }

    private function loadFixtureMetadata(string $name): StreamMetaData
    {
        $fileName = self::FIXTURE_DIR . '/' . $name . '.php';
        $stream   = fopen('php://filter/string.tolower/resource=' . $fileName, 'r');
        assert($stream !== false);
        $source   = file_get_contents($fileName);
        assert($source !== false);
        $metadata = new StreamMetaData($stream, $source);
        fclose($stream);

        return $metadata;
    }

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
