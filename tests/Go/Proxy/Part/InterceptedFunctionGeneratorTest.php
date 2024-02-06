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
use PHPUnit\Framework\TestCase;

use ReflectionFunction;

use function preg_replace;

/**
 * Contains test function with return type to be reflected
 *
 * @return Exception
 */
function funcWithReturnTypeAndDocBlock(): Exception
{
    return new Exception('Test');
}

/**
 * Test case for generated function definition
 */
class InterceptedFunctionGeneratorTest extends TestCase
{
    /**
     * Tests that generator can generate valid function definition
     *
     * @param string $functionName      Name of the function to reflect
     * @param string $expectedSignature Expected function signature
     *
     * @throws \ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerate(string $functionName, string $expectedSignature): void
    {
        $reflectionFunction = new ReflectionFunction($functionName);
        $generator          = new InterceptedFunctionGenerator($reflectionFunction, "\n");

        $generatedCode = $generator->generate();
        // Clean PhpDoc comment, @see https://stackoverflow.com/a/4207149/801258
        $generatedCode = preg_replace('#/\*.+?\*/#s', '', $generatedCode);
        // Remove trailing spaces and empty function body
        $generatedCode = trim($generatedCode, "\n{} ");
        $this->assertSame($expectedSignature, $generatedCode);
    }

    /**
     * Provides list of methods with expected attributes
     */
    public static function dataGenerator(): array
    {
        return [
            [
                'var_dump',
                'function var_dump(mixed $value, mixed ... $values) : void'
            ],
            [
                'array_pop',
                'function array_pop(array &$array) : mixed'
            ],
            [
                'strcoll',
                'function strcoll(string $string1, string $string2) : int'
            ],
            [
                'microtime',
                'function microtime(bool $as_float = false) : float|string'
            ],
            [
                '\Go\Proxy\Part\funcWithReturnTypeAndDocBlock',
                'function funcWithReturnTypeAndDocBlock() : \Exception'
            ],
        ];
    }
}
