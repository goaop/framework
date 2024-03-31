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

use Laminas\Code\Generator\ValueGenerator;
use PHPUnit\Framework\TestCase;

use function class_exists;

class FunctionParameterListTest extends TestCase
{
    /**
     * Tests that generated from reflection parameter list is correct
     *
     * @param string $functionName       Function to reflect
     * @param int    $expectedArgsNumber Number of expected arguments
     * @param array  $checks             List of checks, where key is argument number, starting from 0 and value are getters
     *
     * @throws \ReflectionException if function is not present
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testgetGeneratedParameters(string $functionName, int $expectedArgsNumber, array $checks): void
    {
        $reflection    = new \ReflectionFunction($functionName);
        $parameterList = new FunctionParameterList($reflection);
        $generatedList = $parameterList->getGeneratedParameters();
        $this->assertCount($expectedArgsNumber, $generatedList);
        foreach ($checks as $argumentNumber => $argumentChecks) {
            $generatedArg = $generatedList[$argumentNumber];
            foreach ($argumentChecks as $argumentCheck => $expectedValue) {
                $actualValue = $generatedArg->$argumentCheck();
                if (is_string($expectedValue) && class_exists($expectedValue, false)) {
                    $this->assertInstanceOf($expectedValue, $actualValue);
                } else {
                    $this->assertSame($expectedValue, $actualValue);
                }
            }
        }
    }

    /**
     * Provides list of functions with expected generated args
     */
    public static function dataGenerator(): array
    {
        return [
            [
                'var_dump', // var_dump(mixed $value, mixed ...$values)
                2,
                [
                    [
                        'getPassedByReference' => false,
                        'getVariadic'          => false,
                        'getName'              => 'value',
                        'getDefaultValue'      => null,
                    ]
                ]
            ],
            [
                'array_pop', // array_pop(&$stack)
                1,
                [
                    [
                        'getPassedByReference' => true,
                        'getVariadic'          => false,
                        'getName'              => 'array',
                        'getDefaultValue'      => null,
                    ]
                ]
            ],
            [
                'strcoll', // strcoll($str1, $str2)
                2,
                [
                    [
                        'getPassedByReference' => false,
                        'getVariadic'          => false,
                        'getName'              => 'string1',
                        'getDefaultValue'      => null,
                    ]
                ]
            ],
            [
                'microtime', // microtime($as_float = false)
                1,
                [
                    [
                        'getPassedByReference' => false,
                        'getVariadic'          => false,
                        'getName'              => 'as_float',
                        'getDefaultValue'      => ValueGenerator::class
                    ]
                ]
            ]
        ];
    }
}
