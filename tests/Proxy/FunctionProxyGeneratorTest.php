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
use Go\PhpUnit\AssertsCompilablePhp;
use Go\Stubs\Collision\A\SameNameAspect as AspectA;
use Go\Stubs\Collision\B\SameNameAspect as AspectB;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the global-function proxy generator
 */
class FunctionProxyGeneratorTest extends TestCase
{
    use AssertsCompilablePhp;

    private const string STUBS_FILE = __DIR__ . '/../Stubs/Generator/FunctionGeneratorStubs.php';
    private const string STUBS_NS   = 'Go\Stubs\Generator';

    public function testGenerateWithoutAdvicesProducesEmptyFunctionsFile(): void
    {
        $generator = new FunctionProxyGenerator($this->getStubsNamespace());

        $code = $generator->generate();

        $this->assertStringContainsString('namespace Go\Stubs\Generator;', $code);
        // Framework classes are referenced fully qualified, never imported (see use-collision tests)
        $this->assertStringNotContainsString('use Go\\Aop\\', $code);
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
            "\\Go\\Aop\\Framework\\InterceptorInjector::forFunction(\n        'Go\\Stubs\\Generator\\funcGenHelper_simple',",
            $code,
        );
        $this->assertStringContainsString(
            '\\Go\\Aop\\Framework\\Interceptor::before(\\Go\\Aop\\Framework\\The::advice(\'manual.before\'))',
            $code,
        );
        $this->assertPhpCompiles($code);
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

    public function testGenerateReferencesAspectClassesFullyQualified(): void
    {
        // Two aspects sharing one short name: importing them would be a compile-time fatal error
        $adviceNames = [
            AspectContainer::FUNCTION_PREFIX => [
                'Go\Stubs\Generator\funcGenHelper_noAttr' => [
                    GeneratedInterceptor::fromAdvice('a', new BeforeInterceptor(new AspectA()->beforeMethod(...))),
                    GeneratedInterceptor::fromAdvice('b', new BeforeInterceptor(new AspectB()->beforeMethod(...))),
                ],
            ],
        ];

        $generator = new FunctionProxyGenerator($this->getStubsNamespace(), $adviceNames);
        $code      = $generator->generate();

        $this->assertStringContainsString('\\' . AspectA::class . '::class', $code);
        $this->assertStringContainsString('\\' . AspectB::class . '::class', $code);
        $this->assertStringNotContainsString('use Go\\Stubs', $code);
        $this->assertPhpCompiles($code);
    }

    private function getStubsNamespace(): ReflectionFileNamespace
    {
        return new ReflectionFileNamespace(self::STUBS_FILE, self::STUBS_NS);
    }
}
