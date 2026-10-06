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

use PHPUnit\Framework\TestCase;
use ReflectionFunction;

class FunctionCallArgumentListGeneratorTest extends TestCase
{
    /**
     * Tests that generator can generate function call argument list
     *
     * @throws \ReflectionException if function is not present
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerate(string $functionName, string $expectedLine): void
    {
        $reflection = new ReflectionFunction($functionName);
        $generator  = new FunctionCallArgumentListGenerator($reflection);
        $actualLine = $generator->generate();
        $this->assertSame($expectedLine, $actualLine);
    }

    /**
     * Provides list of functions with expected generated code for calling such functions
     *
     * @return array<array{string, string}>
     */
    public static function dataGenerator(): array
    {
        return [
            ['var_dump', '[$value], $values'],                    // var_dump($value, ...$values)
            ['array_pop', '[&$array]'],               // array_pop(&$stack)
            ['array_diff_assoc', '[$array], $arrays'], // array_diff_assoc($arr1, array ...$arrays)
            ['strcoll', '[$string1, $string2]'],            // strcoll($string1, $string2)
            // basename($path, $suffix = '')
            ['basename', '($__argsCount = \func_num_args()) >= 2 ? [$path, $suffix] : \array_slice([$path, $suffix], 0, $__argsCount)'],
        ];
    }

    /**
     * The generated list holds exactly the passed arguments, like array_slice(…, func_num_args()) on every call did
     */
    public function testGeneratedListHoldsOnlyThePassedArguments(): void
    {
        $function = static function ($first, $second = 2, &$third = 3, ...$rest): array {
            return [];
        };
        $code = (new FunctionCallArgumentListGenerator(new ReflectionFunction($function)))->generate();
        $this->assertSame(
            '($__argsCount = \func_num_args()) >= 3 ? [$first, $second, &$third]'
            . ' : \array_slice([$first, $second, &$third], 0, $__argsCount), $rest',
            $code,
        );

        // Evaluates the generated list in a function with the same signature (test code only)
        $listOf = eval('return static function ($first, $second = 2, &$third = 3, ...$rest): array { return [' . $code . ']; };');
        $this->assertInstanceOf(\Closure::class, $listOf);

        $this->assertSame([[1], []], $listOf(1));
        $this->assertSame([[1, 'b'], []], $listOf(1, 'b'));
        $third = 'c';
        $this->assertSame([[1, 'b', 'c'], ['d', 'e']], $listOf(1, 'b', $third, 'd', 'e'));

        // The by-reference argument stays a reference in the list
        $lists = $listOf(1, 'b', $third);
        $this->assertIsArray($lists);
        $list = $lists[0];
        $this->assertIsArray($list);
        $list[2] = 'changed';
        $this->assertSame($list[2], $third, 'The list must hold a reference to the passed variable');
    }
}
