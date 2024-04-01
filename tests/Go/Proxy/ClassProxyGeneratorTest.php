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

use Go\ParserReflection\ReflectionEngine;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Stubs\First;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

use function substr;

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

        // To prevent deep analysis of parents, we just cut everything after "extends"
        $proxyFileContent = preg_replace('/extends.*/', '', $proxyFileContent);
        $proxyFileAST     = ReflectionEngine::parseFile('/dev/null', $proxyFileContent);

        $proxyFile  = new ReflectionFile('/dev/null', $proxyFileAST);
        $namespaces = $proxyFile->getFileNamespaces();

        // Generated proxy should contain only one single namespace for all test cases
        $this->assertCount(1, $namespaces);
        $expectedNamespace = $reflectionClass->getNamespaceName();
        $this->assertSame($expectedNamespace, $namespaces[$expectedNamespace]->getName());

        // We should have exactly one class with the same name as original one
        $proxyClass = $namespaces[$expectedNamespace]->getClass($className);

        $proxyHasJoinpointProperty = $proxyClass->hasProperty(JoinPointPropertyGenerator::NAME);
        $this->assertTrue($proxyHasJoinpointProperty, 'Child should have joinpoint property in it');
        $joinPoints = $proxyClass->getStaticPropertyValue(JoinPointPropertyGenerator::NAME);
        $this->assertSame($classAdvices, $joinPoints);

        $this->assertTrue($proxyClass->hasMethod($methodName));
        $interceptedMethod = $proxyClass->getMethod($methodName);
        $methodStartPos = $interceptedMethod->getNode()->stmts[0]->getAttribute('startFilePos');
        $methodEndPos   = $interceptedMethod->getNode()->stmts[0]->getAttribute('endFilePos');
        $methodBody     = substr($proxyFileContent, $methodStartPos, ($methodEndPos-$methodStartPos));
        $this->assertStringStartsWith("return self::\$__joinPoints['method:{$methodName}']->__invoke(", $methodBody);
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
