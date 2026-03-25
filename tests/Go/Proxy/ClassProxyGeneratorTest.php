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

namespace Go\Proxy;

use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Stubs\First;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test case for generated function definition
 */
class ClassProxyGeneratorTest extends TestCase
{
    /**
     * Test proxy generation for class method
     *
     * @param string $className Name of the class to intercept
     * @param string $methodName Name of the method to intercept
     *
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerateProxyMethod(string $className, string $methodName): void
    {
        $reflectionClass = new ReflectionClass($className);
        $classAdvices    = [
            'method' => [
                $methodName => ['test']
            ]
        ];

        $childGenerator = new ClassProxyGenerator(
            $reflectionClass,
            'Test',
            $classAdvices,
            false
        );
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        // Proxy uses a trait alias for each intercepted method
        $this->assertStringContainsString(
            "__aop__{$methodName}",
            $proxyFileContent,
            'Proxy must contain trait alias for intercepted method'
        );

        // Proxy declares the static $__joinPoints property
        $this->assertStringContainsString(
            JoinPointPropertyGenerator::NAME,
            $proxyFileContent,
            'Proxy must have $__joinPoints property'
        );

        // Proxy intercepted method delegates to the join-point invocation chain
        $this->assertStringContainsString(
            "self::\$__joinPoints['method:{$methodName}']->__invoke(",
            $proxyFileContent,
            'Proxy method body must delegate to the join-point invocation chain'
        );
    }

    /**
     * Tests that a proxy with property interception uses PropertyInterceptionTrait and generates the
     * correct constructor setup: bindTo with self::class scope and the property accessor closure.
     *
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterception(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'prop' => [
                'public'    => ['test'],
                'protected' => ['test'],
            ]
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            'PropertyInterceptionTrait',
            $proxyFileContent,
            'Proxy with property advices must use PropertyInterceptionTrait'
        );
        $this->assertStringContainsString(
            'self::class',
            $proxyFileContent,
            'Property accessor closure must be bound with self::class scope (not parent::class)'
        );
        $this->assertStringNotContainsString(
            'parent::class',
            $proxyFileContent,
            'Generated proxy must not reference parent::class — the new trait engine has no parent'
        );
        $this->assertStringContainsString(
            '__properties',
            $proxyFileContent,
            'Generated proxy must initialise $__properties via the accessor'
        );
    }

    /**
     * Tests that a proxy for a class that defines its own constructor AND has intercepted properties
     * generates a __aop____construct alias and calls it via $this->__aop____construct() instead of
     * parent::__construct(), which would fail because the new trait-based proxy has no parent class.
     *
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterceptionAndConstructor(): void
    {
        // Use a stub class that has both intercepted properties and its own __construct
        $target          = new class(42) {
            public int $value;
            protected string $name = 'test';

            public function __construct(int $initial)
            {
                $this->value = $initial;
            }
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'value' => ['test'],
                'name'  => ['test'],
            ]
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            '__aop____construct',
            $proxyFileContent,
            'Proxy must alias the original __construct as __aop____construct in the trait-use block'
        );
        $this->assertStringContainsString(
            '$this->__aop____construct(',
            $proxyFileContent,
            'Proxy constructor must call $this->__aop____construct() to invoke the original constructor body'
        );
        $this->assertStringNotContainsString(
            'parent::__construct',
            $proxyFileContent,
            'Proxy must not use parent::__construct — there is no parent class in the trait-based engine'
        );
    }

    /**
     * Regression test: when an aspect only introduces interfaces/traits (no method advices),
     * the original class body trait must still be included in the proxy's use block.
     * Without this, all original class methods are invisible on the proxy instance.
     *
     * @throws ReflectionException
     */
    public function testGenerateWithIntroductionOnlyAlwaysIncludesOriginalTrait(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        // Only interface/trait introductions — no method, static, or property advices
        $classAdvices = [
            'interface' => ['root' => ['\\Stringable']],
            'trait'     => ['root' => ['\\SomeTrait']],
        ];

        $traitName        = 'OriginalBodyTrait';
        $childGenerator   = new ClassProxyGenerator($reflectionClass, $traitName, $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            $traitName,
            $proxyFileContent,
            'Proxy must use the original class body trait even when no methods are intercepted'
        );
    }

    /**
     * Provides list of methods with expected attributes
     *
     * @return array
     */
    public static function dataGenerator(): array
    {
        return [
            [First::class, 'publicMethod'],
            [First::class, 'protectedMethod'],
            [First::class, 'passByReference'],
            [\ClassWithoutNamespace::class, 'publicMethod'],
        ];
    }
}
