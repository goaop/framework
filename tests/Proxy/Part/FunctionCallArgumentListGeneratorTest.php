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
            ['array_pop', '\\func_num_args() > 1 ? [&$array] + \\func_get_args() : [&$array]'], // array_pop(&$array)
            ['array_diff_assoc', '[$array], $arrays'], // array_diff_assoc($arr1, array ...$arrays)
            // strcoll($string1, $string2)
            ['strcoll', '\\func_num_args() > 2 ? [$string1, $string2] + \\func_get_args() : [$string1, $string2]'],
            // basename($path, $suffix = '')
            ['basename', 'match (\\func_num_args()) { 1 => [$path], 2 => [$path, $suffix], default => [$path, $suffix] + \\func_get_args() }'],
            // setcookie($name, $value = '', ... five more optional parameters): too many arms for a match
            ['setcookie', '\\func_num_args() > 7'
                . ' ? [$name, $value, $expires_or_options, $path, $domain, $secure, $httponly] + \\func_get_args()'
                . ' : \\array_slice([$name, $value, $expires_or_options, $path, $domain, $secure, $httponly], 0, \\func_num_args())'],
            // phpversion($extension = null)
            ['phpversion', 'match (\\func_num_args()) { 0 => [], 1 => [$extension], default => [$extension] + \\func_get_args() }'],
            ['time', '\\func_get_args()'],                       // time(): only extra arguments
        ];
    }

    /**
     * The generated list holds exactly the passed arguments, like array_slice(…, func_num_args()) did
     */
    public function testGeneratedListHoldsOnlyThePassedArguments(): void
    {
        $function = static function ($first, $second = 2, &$third = 3, ...$rest): array {
            return [];
        };
        $code = (new FunctionCallArgumentListGenerator(new ReflectionFunction($function)))->generate();
        $this->assertSame('match (\func_num_args()) { 1 => [$first], 2 => [$first, $second], default => [$first, $second, &$third] }, $rest', $code);

        // Evaluates the generated list in a function with the same signature (test code only)
        $listOf = eval('return static function ($first, $second = 2, &$third = 3, ...$rest): array { return [' . $code . ']; };');
        $this->assertInstanceOf(\Closure::class, $listOf);

        $this->assertSame([[1], []], $listOf(1));
        $this->assertSame([[1, 'b'], []], $listOf(1, 'b'));
        $third = 'c';
        $this->assertSame([[1, 'b', 'c'], ['d', 'e']], $listOf(1, 'b', $third, 'd', 'e'));
    }

    /**
     * Extra arguments passed after the declared ones reach the original function too, like the declared ones
     */
    public function testGeneratedListKeepsExtraArgumentsAndReferences(): void
    {
        $function = static function ($first, &$second, $third = 3): array {
            return [];
        };
        $code = (new FunctionCallArgumentListGenerator(new ReflectionFunction($function)))->generate();
        $this->assertSame(
            'match (\\func_num_args()) { 2 => [$first, &$second], 3 => [$first, &$second, $third], default => [$first, &$second, $third] + \\func_get_args() }',
            $code,
        );

        // Evaluates the generated list in a function with the same signature (test code only)
        $listOf = eval('return static function ($first, &$second, $third = 3): array { return ' . $code . '; };');
        $this->assertInstanceOf(\Closure::class, $listOf);

        $second = 'b';
        $this->assertSame([1, 'b'], $listOf(1, $second));
        $this->assertSame([1, 'b', 'c', 'd', 'e'], $listOf(1, $second, 'c', 'd', 'e'));

        // The declared argument stays a reference in the list with the extra arguments
        $list = $listOf(1, $second, 'c', 'd');
        $this->assertIsArray($list);
        $list[1] = 'changed';
        $this->assertSame('changed', $list[1]);
        $this->assertSame($list[1], $second, 'The list must hold a reference to the passed variable');
    }

    public function testGeneratedListOfFunctionWithoutArgumentsHoldsTheExtraArguments(): void
    {
        $code = (new FunctionCallArgumentListGenerator(new ReflectionFunction(static fn(): null => null)))->generate();
        $this->assertSame('\\func_get_args()', $code);

        // Evaluates the generated list in a function with the same signature (test code only)
        $listOf = eval('return static function (): array { return ' . $code . '; };');
        $this->assertInstanceOf(\Closure::class, $listOf);

        $this->assertSame([], $listOf());
        $this->assertSame(['a', 'b'], $listOf('a', 'b'));
    }
}
