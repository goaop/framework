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

use Go\Aop\Features;
use Go\Instrument\Transformer\ConstructorExecutionTransformer;
use Go\Instrument\Transformer\FilterInjectorTransformer;
use Go\Instrument\Transformer\MagicConstantTransformer;
use Go\Instrument\Transformer\WeavingTransformer;
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

    public function testGetContainerReturnsInjectedContainer(): void
    {
        $kernel    = $this->makeKernel();
        $container = new Container();

        $containerProperty = new ReflectionProperty(AspectKernel::class, 'container');
        $containerProperty->setValue($kernel, $container);

        $this->assertSame($container, $kernel->getContainer());
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

    public function testNormalizeOptionsThrowsWithoutCacheDir(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('You need to provide valid cache directory for Go! AOP framework.');

        $this->invokeProtected($kernel, 'normalizeOptions', [[]]);
    }

    public function testNormalizeOptionsThrowsForContainerClassNotExtendingAspectContainer(): void
    {
        $kernel = $this->makeKernel();

        $this->expectException(RuntimeException::class);
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

    public function testNormalizeOptionsFallsBackToDefaultContainerClassForUnknownClassString(): void
    {
        $kernel = $this->makeKernel();

        $normalized = $this->invokeProtectedArray($kernel, 'normalizeOptions', [[
            'cacheDir'       => '/some/cache/dir',
            'containerClass' => 'Totally\\Unknown\\ClassThatDoesNotExist',
        ]]);

        $this->assertSame(Container::class, $normalized['containerClass']);
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
