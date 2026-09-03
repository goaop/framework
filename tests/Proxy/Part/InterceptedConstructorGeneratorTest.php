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

namespace Go\Proxy\Part;

use Exception;
use Go\Stubs\Constructor\ClassWithOptionalArgsConstructor;
use Go\Stubs\Constructor\ClassWithoutConstructor;
use Go\Stubs\Constructor\ClassWithPrivateConstructor;
use Go\Stubs\Constructor\ClassWithProtectedConstructor;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function preg_replace;

/**
 * Test case for generated method definition
 */
class InterceptedConstructorGeneratorTest extends TestCase
{
    /**
     * Tests that generator can generate valid method definition
     *
     * @param class-string $className
     *
     * @throws \ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerate(string $className, string $expectedSignature): void
    {
        $reflectionConstructor = (new ReflectionClass($className))->getConstructor();
        $generator             = new InterceptedConstructorGenerator($reflectionConstructor);

        $generatedCode = preg_replace('/^\s+/m', '', $generator->generate());
        $expectedCode  = preg_replace('/^\s+/m', '', $expectedSignature);
        assert($generatedCode !== null && $expectedCode !== null);
        $this->assertStringMatchesFormat($expectedCode, $generatedCode);
    }

    /**
     * Provides list of methods with expected attributes
     *
     * @return array<array{class-string, string}>
     */
    public static function dataGenerator(): array
    {
        return [
            [
                Exception::class,
                'public function __construct(string $message = \'\', int $code = 0, ?\Throwable $previous = null)
                {
                    parent::__construct(...\array_slice([$message, $code, $previous], 0, \func_num_args()));
                }',
            ],
            [
                ClassWithOptionalArgsConstructor::class,
                'public function __construct(int $foo = 42, bool $bar = false, ?\stdClass $instance = null)
                {
                    parent::__construct(...\array_slice([$foo, $bar, $instance], 0, \func_num_args()));
                }',
            ],
            [
                ClassWithoutConstructor::class,
                'public function __construct()
                {
                }',
            ],
            [
                ClassWithProtectedConstructor::class,
                'protected function __construct(string $className, int &$byReference)
                {
                    parent::__construct(...[$className, &$byReference]);
                }',
            ],
        ];
    }

    /**
     * When the constructor belongs to the class being proxied (trait-based engine), it is placed in the
     * trait body and aliased as __aop____construct. The generated constructor must call
     * $this->__aop____construct() rather than parent::__construct(), which would fail because the new
     * proxy class has no parent.
     */
    public function testGenerateWithConstructorInTrait(): void
    {
        $reflectionConstructor = (new ReflectionClass(ClassWithOptionalArgsConstructor::class))->getConstructor();
        $generator             = new InterceptedConstructorGenerator(
            $reflectionConstructor,
            null,
            true, // $constructorIsInTrait
        );

        $generatedCode = $generator->generate();

        $this->assertStringContainsString(
            '$this->__aop____construct(',
            $generatedCode,
            'When constructorIsInTrait=true, must call $this->__aop____construct() instead of parent::__construct()',
        );
        $this->assertStringNotContainsString(
            'parent::__construct',
            $generatedCode,
            'When constructorIsInTrait=true, must NOT use parent::__construct',
        );
        $this->assertStringNotContainsString(
            'bindTo',
            $generatedCode,
            'Property accessor must not use bindTo and closures at all',
        );
    }

    public function testNotThrowsExceptionForPrivateConstructor(): void
    {
        $reflectionConstructor = (new ReflectionClass(ClassWithPrivateConstructor::class))->getConstructor();
        $generator     = new InterceptedConstructorGenerator($reflectionConstructor);
        $generatedCode = $generator->generate();
        $this->assertStringContainsString(
            'private function __construct',
            $generatedCode,
            'When constructor is private, must not throw exception',
        );
    }

    public function testGeneratesEmptyConstructorWhenNoneGiven(): void
    {
        $generator = new InterceptedConstructorGenerator();

        $this->assertStringContainsString('function __construct()', $generator->generate());
    }

    public function testReusesProvidedConstructorGenerator(): void
    {
        $reflectionConstructor  = (new ReflectionClass(ClassWithOptionalArgsConstructor::class))->getConstructor();
        $this->assertNotNull($reflectionConstructor);
        $intercepted            = new InterceptedMethodGenerator($reflectionConstructor, 'echo "custom body";');

        $generator = new InterceptedConstructorGenerator($reflectionConstructor, $intercepted);

        $this->assertStringContainsString('echo "custom body";', $generator->generate());
    }

    public function testAccessorsExposeUnderlyingGeneratorState(): void
    {
        $reflectionConstructor = (new ReflectionClass(ClassWithOptionalArgsConstructor::class))->getConstructor();
        $generator              = new InterceptedConstructorGenerator($reflectionConstructor);

        $this->assertSame('__construct', $generator->getName());
        $this->assertStringContainsString('parent::__construct', $generator->getBody());

        $generator->setBody('parent::__construct();');
        $this->assertSame('parent::__construct();', $generator->getBody());

        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(\PhpParser\Node\Stmt\ClassMethod::class, $generator->getNode());
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(\Go\Proxy\Generator\MethodGenerator::class, $generator->getGenerator());
    }
}
