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
