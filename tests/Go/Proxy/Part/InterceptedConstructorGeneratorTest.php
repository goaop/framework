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
use function preg_replace;
use ReflectionClass;

/**
 * Test case for generated method definition
 */
class InterceptedConstructorGeneratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Tests that generator can generate valid method definition
     *
     * @param string $className
     * @param string $expectedSignature
     *
     * @throws \ReflectionException
     * @dataProvider dataGenerator
     */
    public function testGenerate(string $className, string $expectedSignature)
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
     *
     * @return array
     */
    public function dataGenerator(): array
    {
        return [
            [
                Exception::class,
                'public function __construct($message = null, $code = null, $previous = null)
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

    public function testGenerateWithProperties()
    {
        $reflectionConstructor = (new ReflectionClass(ClassWithoutConstructor::class))->getConstructor();
        $generator             = new InterceptedConstructorGenerator(['foo', 'bar'], $reflectionConstructor);

        $generatedCode = $generator->generate();
        $generatedCode = preg_replace('/^\s+|\s+$/m', '', $generatedCode);
        $expectedCode  = preg_replace('/^\s+|\s+$/m', '', '
            public function __construct()
            {
                $this->__properties = [
                    \'foo\' => &$this->foo,
                    \'bar\' => &$this->bar
                ];
                unset(
                    $this->foo,
                    $this->bar
                );
            }'
        );
        $this->assertSame($expectedCode, $generatedCode);
    }

    public function testThrowsExceptionForPrivateConstructor()
    {
        $this->expectException(\LogicException::class);

        $reflectionConstructor = (new ReflectionClass(ClassWithPrivateConstructor::class))->getConstructor();
        new InterceptedConstructorGenerator(['foo', 'bar'], $reflectionConstructor);
    }
}
