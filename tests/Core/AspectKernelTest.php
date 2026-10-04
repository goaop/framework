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

namespace Go\Core;

use Go\Aop\AspectException;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Features;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\NodeRewriter;
use Go\Instrument\Transformer\SourceTransformer;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\SyntaxTreeRewriter;
use Go\Instrument\Transformer\TransformerResult;
use Go\Instrument\Transformer\WeavingTransformer;
use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

class AspectKernelTest extends TestCase
{
    protected function tearDown(): void
    {
        // AspectKernel::$instance is a single process-wide static shared by every subclass
        // (self::$instance resolves against the declaring class). Reset it so other test
        // classes calling ConcreteKernel::getInstance() get a fresh instance of their own.
        $instance = new ReflectionProperty(AspectKernel::class, 'instance');
        $instance->setValue(null, null);
    }

    /**
     * Builds a minimal concrete kernel without running the (unsafe to run twice /
     * process-wide side-effecting) constructor or init() flow.
     */
    private function makeKernel(): AspectKernelTestConcreteKernel
    {
        /** @var AspectKernelTestConcreteKernel $kernel */
        $kernel = (new ReflectionClass(AspectKernelTestConcreteKernel::class))->newInstanceWithoutConstructor();

        return $kernel;
    }

    /**
     * @param array<mixed> $args
     */
    private function invokeProtected(object $object, string $method, array $args = []): mixed
    {
        $reflectionMethod = new ReflectionMethod(AspectKernel::class, $method);

        return $reflectionMethod->invokeArgs($object, $args);
    }

    /**
     * @param array<mixed> $args
     * @return array<string, mixed>
     */
    private function invokeProtectedArray(object $object, string $method, array $args = []): array
    {
        $result = $this->invokeProtected($object, $method, $args);

        if (!is_array($result)) {
            throw new RuntimeException(sprintf('Expected %s::%s() to return an array', $object::class, $method));
        }

        $typed = [];
        foreach ($result as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException(sprintf('Expected %s::%s() to return a string-keyed array', $object::class, $method));
            }
            $typed[$key] = $value;
        }

        return $typed;
    }

    /**
     * @param array<mixed> $args
     */
    private function invokeProtectedString(object $object, string $method, array $args = []): string
    {
        $result = $this->invokeProtected($object, $method, $args);

        if (!is_string($result)) {
            throw new RuntimeException(sprintf('Expected %s::%s() to return a string', $object::class, $method));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getKernelOptions(object $kernel): array
    {
        $optionsProperty = new ReflectionProperty(AspectKernel::class, 'options');
        $options         = $optionsProperty->getValue($kernel);

        if (!is_array($options)) {
            throw new RuntimeException('Expected AspectKernel::$options to be an array');
        }

        $typed = [];
        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Expected AspectKernel::$options to be a string-keyed array');
            }
            $typed[$key] = $value;
        }

        return $typed;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function setKernelOptions(object $kernel, array $overrides): void
    {
        $optionsProperty = new ReflectionProperty(AspectKernel::class, 'options');
        $optionsProperty->setValue($kernel, [...$this->getKernelOptions($kernel), ...$overrides]);
    }

    public function testGetInstanceReturnsSameSingletonInstanceOnRepeatedCalls(): void
    {
        $first  = AspectKernelTestConcreteKernel::getInstance();
        $second = AspectKernelTestConcreteKernel::getInstance();

        $this->assertSame($first, $second);
        $this->assertInstanceOf(AspectKernelTestConcreteKernel::class, $first);
    }

    public function testGetInstanceOfTheAbstractKernelThrowsClearExceptionBeforeAnyKernelExists(): void
    {
        $instanceProperty = new ReflectionProperty(AspectKernel::class, 'instance');
        $existingInstance = $instanceProperty->getValue();
        $instanceProperty->setValue(null, null);

        try {
            $this->expectException(AspectException::class);
            $this->expectExceptionMessage('Aspect kernel is not initialized yet');

            AspectKernel::getInstance();
        } finally {
            $instanceProperty->setValue(null, $existingInstance);
        }
    }

    public function testGetContainerReturnsInjectedContainer(): void
    {
        $kernel    = $this->makeKernel();
        $container = new Container();

        $containerProperty = new ReflectionProperty(AspectKernel::class, 'container');
        $containerProperty->setValue($kernel, $container);

        $this->assertSame($container, $kernel->getContainer());
    }

    public function testGetContainerBeforeInitThrowsClearException(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('is not initialized yet, call init() first');

        $this->makeKernel()->getContainer();
    }

    public function testHasFeatureReturnsTrueOnlyWhenBitIsSet(): void
    {
        $kernel = $this->makeKernel();

        $this->setKernelOptions($kernel, [
            'features' => Features::INTERCEPT_FUNCTIONS | Features::INTERCEPT_INCLUDES,
        ]);

        $this->assertTrue($kernel->hasFeature(Features::INTERCEPT_FUNCTIONS));
        $this->assertTrue($kernel->hasFeature(Features::INTERCEPT_INCLUDES));
        $this->assertFalse($kernel->hasFeature(Features::INTERCEPT_INITIALIZATIONS));
        $this->assertFalse($kernel->hasFeature(Features::PREBUILT_CACHE));
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function unknownFeatures(): iterable
    {
        yield 'zero' => [0];
        yield 'undefined bit' => [8];
        yield 'defined and undefined bits' => [Features::PREBUILT_CACHE | 128];
    }

    #[DataProvider('unknownFeatures')]
    public function testHasFeatureRejectsUnknownFeature(int $feature): void
    {
        $kernel = $this->makeKernel();
        $this->setKernelOptions($kernel, ['features' => Features::ALL]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage("Unknown feature {$feature}");

        $kernel->hasFeature($feature);
    }

    public function testGetOptionsReturnsCurrentlyStoredOptions(): void
    {
        $kernel = $this->makeKernel();

        $options = [...$this->getKernelOptions($kernel), 'appDir' => '/some/app/dir'];
        $this->setKernelOptions($kernel, $options);

        $this->assertSame($options, $kernel->getOptions());
    }

    public function testGetDefaultOptionsReturnsExpectedShapeAndContainerClass(): void
    {
        $kernel  = $this->makeKernel();
        $default = $this->invokeProtectedArray($kernel, 'getDefaultOptions');

        $this->assertSame(
            ['debug', 'appDir', 'cacheDir', 'cacheFileMode', 'features', 'includePaths', 'excludePaths', 'containerClass'],
            array_keys($default),
        );
        $this->assertFalse($default['debug']);
        $this->assertNull($default['cacheDir']);
        $this->assertSame(0, $default['features']);
        $this->assertSame([], $default['includePaths']);
        $this->assertSame([], $default['excludePaths']);
        $this->assertSame(Container::class, $default['containerClass']);
    }

    public function testNormalizeOptionsExcludesRuntimeDependenciesFromWeaving(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [['cacheDir' => '/some/cache/dir']]);

        $excludePaths = $normalized['excludePaths'];
        $this->assertIsArray($excludePaths);
        foreach (['goaop/dissect', 'goaop/parser-reflection', 'nikic/php-parser', 'symfony/finder'] as $package) {
            $this->assertContains(realpath(__DIR__ . '/../../vendor/' . $package), $excludePaths, $package);
        }
        $this->assertNotContains(realpath(__DIR__ . '/../..'), $excludePaths, 'The application root must stay woven');
    }

    public function testNormalizeOptionsThrowsWithoutCacheDir(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('You need to provide valid cache directory for Go! AOP framework.');

        $this->invokeProtected($kernel, 'normalizeOptions', [[]]);
    }

    public function testNormalizeOptionsThrowsForContainerClassNotExtendingAspectContainer(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(sprintf(
            'Container class "%s" must extend %s.',
            self::class,
            AspectContainer::class,
        ));

        $this->invokeProtected($kernel, 'normalizeOptions', [[
            'cacheDir'       => '/some/cache/dir',
            'containerClass' => self::class,
        ]]);
    }

    public function testNormalizeOptionsThrowsForUnknownContainerClass(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Container class "Totally\\Unknown\\ClassThatDoesNotExist" does not exist.');

        $this->invokeProtected($kernel, 'normalizeOptions', [[
            'cacheDir'       => '/some/cache/dir',
            'containerClass' => 'Totally\\Unknown\\ClassThatDoesNotExist',
        ]]);
    }

    public function testNormalizeOptionsAcceptsZeroAsCacheDirName(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [['cacheDir' => '0']]);

        $this->assertSame('0', $normalized['cacheDir']);
    }

    public function testNormalizeOptionsRejectsUnknownOptionWithSuggestion(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown kernel option "cachedir", did you mean "cacheDir"?');

        $this->invokeProtected($kernel, 'normalizeOptions', [[
            'cacheDir' => '/some/cache/dir',
            'cachedir' => '/some/other/dir',
        ]]);
    }

    public function testNormalizeOptionsRejectsUnknownOptionWithoutSuggestion(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown kernel option "frontController". Known options are: debug, appDir');

        $this->invokeProtected($kernel, 'normalizeOptions', [[
            'cacheDir'        => '/some/cache/dir',
            'frontController' => '/web/index.php',
        ]]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidFeatures(): iterable
    {
        yield 'unknown bit' => [8];
        yield 'known and unknown bits' => [Features::INTERCEPT_FUNCTIONS | 128];
        yield 'string' => ['1'];
        yield 'boolean' => [true];
    }

    #[DataProvider('invalidFeatures')]
    public function testNormalizeOptionsRejectsInvalidFeatures(mixed $features): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Option "features" must be a combination of Go\\Aop\\Features constants');

        $this->invokeProtected($kernel, 'normalizeOptions', [[
            'cacheDir' => '/some/cache/dir',
            'features' => $features,
        ]]);
    }

    public function testNormalizeOptionsAcceptsEveryKnownFeature(): void
    {
        $kernel   = $this->makeKernel();
        $features = Features::INTERCEPT_FUNCTIONS | Features::INTERCEPT_INITIALIZATIONS
            | Features::INTERCEPT_INCLUDES | Features::PREBUILT_CACHE;

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'cacheDir' => '/some/cache/dir',
            'features' => $features,
        ]]);

        $this->assertSame($features, $normalized['features']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidCacheFileModes(): iterable
    {
        yield 'zero' => [0];
        yield 'no owner write' => [0444];
        yield 'out of range' => [01777];
        yield 'negative' => [-1];
        yield 'string' => ['0644'];
    }

    public function testNormalizeOptionsDerivesCacheFileModeFromUmaskWhenNotSet(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'cacheDir'      => '/some/cache/dir',
            'cacheFileMode' => null,
        ]]);

        $this->assertSame(0770 & ~umask(), $normalized['cacheFileMode']);
    }

    #[DataProvider('invalidCacheFileModes')]
    public function testNormalizeOptionsRejectsInvalidCacheFileMode(mixed $cacheFileMode): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Option "cacheFileMode" must be an integer permission mask');

        $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'cacheDir'      => '/some/cache/dir',
            'cacheFileMode' => $cacheFileMode,
        ]]);
    }

    public function testNormalizeOptionsResolvesAndMergesGivenOptions(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'debug'          => true,
            'cacheDir'       => '/some/cache/dir',
            'cacheFileMode'  => 0644,
            'features'       => Features::INTERCEPT_FUNCTIONS,
            'includePaths'   => ['/some/include'],
            'excludePaths'   => ['/some/exclude'],
            'containerClass' => Container::class,
        ]]);

        $this->assertTrue($normalized['debug']);
        $this->assertSame(0644, $normalized['cacheFileMode']);
        $this->assertSame(Features::INTERCEPT_FUNCTIONS, $normalized['features']);
        $this->assertSame(Container::class, $normalized['containerClass']);

        $cacheDir = $normalized['cacheDir'];
        $this->assertIsString($cacheDir);
        $this->assertStringContainsString('cache', $cacheDir);

        $includePaths = $normalized['includePaths'];
        $this->assertIsArray($includePaths);
        $this->assertArrayHasKey(0, $includePaths);
        $this->assertIsString($includePaths[0]);
        $this->assertStringContainsString('some', $includePaths[0]);

        $excludePaths = $normalized['excludePaths'];
        $this->assertIsArray($excludePaths);
        // excludePaths always grows with the resolved cache dir and the framework's own src dir
        $this->assertGreaterThanOrEqual(3, count($excludePaths));
        foreach ($excludePaths as $excludePath) {
            $this->assertIsString($excludePath);
        }
    }

    public function testNormalizeOptionsIgnoresNonStringEntriesInPathLists(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'cacheDir'     => '/some/cache/dir',
            'includePaths' => ['/valid/path', 123, null],
            'excludePaths' => ['/valid/exclude', false],
        ]]);

        $includePaths = $normalized['includePaths'];
        $this->assertIsArray($includePaths);
        foreach ($includePaths as $includePath) {
            $this->assertIsString($includePath);
        }

        $excludePaths = $normalized['excludePaths'];
        $this->assertIsArray($excludePaths);
        foreach ($excludePaths as $excludePath) {
            $this->assertIsString($excludePath);
        }
    }

    public function testRegisterTransformerServicesAlwaysRegistersWeavingAndMagicConstantTransformers(): void
    {
        $kernel = $this->makeKernel();

        $this->setKernelOptions($kernel, ['features' => 0]);

        $container = new Container();
        $this->invokeProtected($kernel, 'registerTransformerServices', [$container]);

        $this->assertTrue($container->has(WeavingTransformer::class));
        $this->assertTrue($container->has(MagicConstantTransformer::class));
        $this->assertFalse($container->has(ConstructorExecutionTransformer::class));
        $this->assertFalse($container->has(FilterInjectorTransformer::class));
    }

    public function testRegisterTransformerServicesRegistersConstructorExecutionTransformerWhenFeatureEnabled(): void
    {
        $kernel = $this->makeKernel();

        $this->setKernelOptions($kernel, ['features' => Features::INTERCEPT_INITIALIZATIONS]);

        $container = new Container();
        $this->invokeProtected($kernel, 'registerTransformerServices', [$container]);

        $this->assertTrue($container->has(ConstructorExecutionTransformer::class));
        $this->assertFalse($container->has(FilterInjectorTransformer::class));
    }

    public function testRegisterTransformerServicesRegistersFilterInjectorTransformerWhenFeatureEnabled(): void
    {
        $kernel = $this->makeKernel();

        $this->setKernelOptions($kernel, ['features' => Features::INTERCEPT_INCLUDES]);

        $container = new Container();
        $this->invokeProtected($kernel, 'registerTransformerServices', [$container]);

        $this->assertTrue($container->has(FilterInjectorTransformer::class));
        $this->assertFalse($container->has(ConstructorExecutionTransformer::class));
    }

    /**
     * External projects register their own transformers and node rewriters as container services
     * from configureAop(): transformers join the chain after the built-in ones, rewriters join the
     * single syntax tree walk that opens the chain
     */
    public function testCustomTransformersAndNodeRewritersJoinTheTransformationChain(): void
    {
        $kernel    = $this->makeKernel();
        $container = $this->registerPipeline($kernel);

        $this->assertSame(
            [SyntaxTreeRewriter::class, WeavingTransformer::class, AspectKernelTestCustomTransformer::class],
            array_keys($container->getServicesByInterface(SourceTransformer::class)),
        );
        $this->assertCustomNodeRewriterIsApplied($container);
    }

    /**
     * A kernel replacing the built-in transformers (e.g. AspectMock) keeps the syntax tree walk,
     * so node rewriters registered by external projects keep working
     */
    public function testNodeRewritersStillApplyWhenTransformerServicesAreOverridden(): void
    {
        /** @var AspectKernelTestCustomPipelineKernel $kernel */
        $kernel    = (new ReflectionClass(AspectKernelTestCustomPipelineKernel::class))->newInstanceWithoutConstructor();
        $container = $this->registerPipeline($kernel);

        $this->assertSame(
            [SyntaxTreeRewriter::class, AspectKernelTestCustomTransformer::class],
            array_keys($container->getServicesByInterface(SourceTransformer::class)),
        );
        $this->assertCustomNodeRewriterIsApplied($container);
    }

    /**
     * Registers the services in the order of AspectKernel::init(), with an external transformer
     * and node rewriter registered the way configureAop() does
     */
    private function registerPipeline(AspectKernel $kernel): Container
    {
        $this->setKernelOptions($kernel, ['features' => 0, 'appDir' => __DIR__, 'cacheDir' => sys_get_temp_dir()]);

        $container = new Container();
        $container->add(AspectKernel::class, $kernel);
        $container->add('kernel.interceptFunctions', false);
        FrameworkServices::register($container);
        // Invoked on the kernel class itself, so an overridden hook is the one that runs
        (new ReflectionMethod($kernel, 'registerTransformerServices'))->invoke($kernel, $container);

        $container->addLazyService(
            AspectKernelTestCustomNodeRewriter::class,
            fn(): AspectKernelTestCustomNodeRewriter => new AspectKernelTestCustomNodeRewriter(),
        );
        $container->addLazyService(
            AspectKernelTestCustomTransformer::class,
            fn(): AspectKernelTestCustomTransformer => new AspectKernelTestCustomTransformer(),
        );

        return $container;
    }

    private function assertCustomNodeRewriterIsApplied(Container $container): void
    {
        $stream = fopen('php://input', 'rb');
        assert($stream !== false);
        $metadata = new StreamMetaData($stream, '<?php echo "original";');

        $result = $container->getService(SyntaxTreeRewriter::class)->transform($metadata);

        $this->assertSame(TransformerResult::Transformed, $result);
        $this->assertSame('<?php echo "custom";', $metadata->source);
    }

    public function testGetFileNameWhereInitializedReturnsCallerFile(): void
    {
        $kernel = $this->makeKernel();

        $file = $this->callGetFileNameWhereInitialized($kernel);

        $this->assertSame(__FILE__, $file);
    }

    /**
     * Thin wrapper so debug_backtrace() inside getFileNameWhereInitialized() sees this call
     * site (the test method above) as the "file where the kernel was initialized".
     */
    private function callGetFileNameWhereInitialized(AspectKernel $kernel): string
    {
        return $this->invokeProtectedString($kernel, 'getFileNameWhereInitialized');
    }
}

final class AspectKernelTestConcreteKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void {}
}

/**
 * Kernel replacing the built-in transformers with its own one, like AspectMock does
 */
final class AspectKernelTestCustomPipelineKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void {}

    protected function registerTransformerServices(AspectContainer $container): void {}
}

final class AspectKernelTestCustomTransformer implements SourceTransformer
{
    public function transform(StreamMetaData $metadata): TransformerResult
    {
        return TransformerResult::Abstain;
    }
}

final class AspectKernelTestCustomNodeRewriter implements NodeRewriter
{
    public function getNodeTypes(): array
    {
        return [String_::class];
    }

    public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool
    {
        $position = $node->getAttribute('startTokenPos');
        if (!is_int($position)) {
            return false;
        }
        $file->tokenStream[$position]->text = '"custom"';

        return true;
    }
}
