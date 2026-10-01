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

use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Stubs\AttributeAspectLoaderExtensionTestPublicAspect;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the global-function proxy generator
 */
class FunctionProxyGeneratorTest extends TestCase
{
    private const string STUBS_FILE = __DIR__ . '/../Stubs/Generator/FunctionGeneratorStubs.php';
    private const string STUBS_NS   = 'Go\Stubs\Generator';

    public function testGenerateWithoutAdvicesProducesEmptyFunctionsFile(): void
    {
        $generator = new FunctionProxyGenerator($this->getStubsNamespace());

        $code = $generator->generate();

        $this->assertStringContainsString('namespace Go\Stubs\Generator;', $code);
        $this->assertStringContainsString('use Go\Aop\Framework\InterceptorInjector;', $code);
        $this->assertStringContainsString('use Go\Aop\Framework\Interceptor;', $code);
        $this->assertStringContainsString('use Go\Aop\Framework\The;', $code);
        $this->assertStringContainsString('use Go\Aop\Intercept\FunctionInvocation;', $code);
        $this->assertStringNotContainsString('function funcGenHelper_', $code);
    }

    public function testGenerateWrapsFunctionWithNormalReturnType(): void
    {
        $advice = GeneratedInterceptor::fromAdvice(
            'manual.before',
            new BeforeInterceptor(static fn(): mixed => null),
        );

        $adviceNames = [
            AspectContainer::FUNCTION_PREFIX => [
                'Go\Stubs\Generator\funcGenHelper_simple' => [$advice],
            ],
        ];

        $generator = new FunctionProxyGenerator($this->getStubsNamespace(), $adviceNames);
        $code      = $generator->generate();

        $this->assertStringContainsString(
            'function funcGenHelper_simple(string $name, int $count = 0): string',
            $code,
        );
        $this->assertStringContainsString(
            "InterceptorInjector::forFunction(\n        'Go\\Stubs\\Generator\\funcGenHelper_simple',",
            $code,
        );
        $this->assertStringContainsString('Interceptor::before(The::advice(\'manual.before\'))', $code);
        $this->assertStringContainsString('\Go\Stubs\Generator\funcGenHelper_simple(...)', $code);
        // Non-void return type: the joinpoint invocation result must be returned.
        $this->assertStringContainsString('return $__joinPoint->__invoke(', $code);
    }

    public function testGenerateWrapsVoidFunctionWithoutReturnStatement(): void
    {
        $advice = GeneratedInterceptor::fromAdvice(
            'manual.before.void',
            new BeforeInterceptor(static fn(): mixed => null),
        );

        $adviceNames = [
            AspectContainer::FUNCTION_PREFIX => [
                'Go\Stubs\Generator\funcGenHelper_void' => [$advice],
            ],
        ];

        $generator = new FunctionProxyGenerator($this->getStubsNamespace(), $adviceNames);
        $code      = $generator->generate();

        $this->assertStringContainsString('function funcGenHelper_void(): void', $code);
        // void return type: the joinpoint must be invoked but its result not returned.
        $this->assertStringContainsString('$__joinPoint->__invoke()', $code);
        $this->assertStringNotContainsString('return $__joinPoint->__invoke()', $code);
    }

    public function testGenerateImportsAspectClassOfBoundAdvice(): void
    {
        $aspect = new AttributeAspectLoaderExtensionTestPublicAspect();
        $advice = GeneratedInterceptor::fromAdvice(
            'manual.before.aspect',
            new BeforeInterceptor($aspect->publicAdvice(...)),
        );

        $adviceNames = [
            AspectContainer::FUNCTION_PREFIX => [
                'Go\Stubs\Generator\funcGenHelper_noAttr' => [$advice],
            ],
        ];

        $generator = new FunctionProxyGenerator($this->getStubsNamespace(), $adviceNames);
        $code      = $generator->generate();

        $this->assertStringContainsString(
            'use ' . AttributeAspectLoaderExtensionTestPublicAspect::class . ';',
            $code,
        );
    }

    private function getStubsNamespace(): ReflectionFileNamespace
    {
        return new ReflectionFileNamespace(self::STUBS_FILE, self::STUBS_NS);
    }
}
