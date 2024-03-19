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
     * @throws \ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerate(string $className, string $expectedSignature): void
    {
        $reflectionConstructor = (new ReflectionClass($className))->getConstructor();
        $generator             = new InterceptedConstructorGenerator([], $reflectionConstructor);

        $generatedCode = $generator->generate();
        $generatedCode = preg_replace('/^\s+/m', '', $generatedCode);
        $expectedCode  = preg_replace('/^\s+/m', '', $expectedSignature);
        $this->assertStringMatchesFormat($expectedCode, $generatedCode);
    }

    /**
     * Provides list of methods with expected attributes
     */
    public static function dataGenerator(): array
    {
        return [
            [
                Exception::class,
                'public function __construct(string $message = \'\', int $code = 0, \Throwable $previous = null)
                {
                    parent::__construct(...\array_slice([$message, $code, $previous], 0, \func_num_args()));
                }'
            ],
            [
                ClassWithOptionalArgsConstructor::class,
                'public function __construct(int $foo = 42, bool $bar = false, \stdClass $instance = null)
                {
                    parent::__construct(...\array_slice([$foo, $bar, $instance], 0, \func_num_args()));
                }'
            ],
            [
                ClassWithoutConstructor::class,
                'public function __construct()
                {
                }'
            ],
            [
                ClassWithProtectedConstructor::class,
                'protected function __construct(string $className, int &$byReference)
                {
                    parent::__construct(...[$className, &$byReference]);
                }'
            ],
        ];
    }

    public function testGenerateWithProperties(): void
    {
        $reflectionConstructor = (new ReflectionClass(ClassWithoutConstructor::class))->getConstructor();
        $generator             = new InterceptedConstructorGenerator(['foo', 'bar'], $reflectionConstructor);

        $generatedCode = $generator->generate();
        $generatedCode = preg_replace('/^\s+|\s+$/m', '', $generatedCode);
        $expectedCode  = preg_replace(
            '/^\s+|\s+$/m',
            '',
            '
            public function __construct()
            {
                $accessor = function(array &$propertyStorage, object $target) {
                    $propertyStorage = [
                        \'foo\' => &$target->foo,
                        \'bar\' => &$target->bar
                    ];
                    unset(
                        $target->foo,
                        $target->bar
                    );
                };
                ($accessor->bindTo($this, parent::class))($this->__properties, $this);
            }'
        );
        $this->assertSame($expectedCode, $generatedCode);
    }

    public function testThrowsExceptionForPrivateConstructor(): void
    {
        $this->expectException(\LogicException::class);

        $reflectionConstructor = (new ReflectionClass(ClassWithPrivateConstructor::class))->getConstructor();
        new InterceptedConstructorGenerator(['foo', 'bar'], $reflectionConstructor);
    }
}
