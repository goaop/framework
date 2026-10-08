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

use Go\Proxy\Generator\GeneratedCodePrinter;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
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
     * Tests that the AST arguments print as the generated code
     *
     * @throws \ReflectionException if function is not present
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGetArgs(string $functionName, string $expectedLine): void
    {
        $reflection = new ReflectionFunction($functionName);
        $generator  = new FunctionCallArgumentListGenerator($reflection);
        $call       = new FuncCall(new Name('f'), $generator->getArgs());

        $this->assertSame("f($expectedLine)", (new GeneratedCodePrinter(['shortArraySyntax' => true]))->prettyPrintExpr($call));
    }

    public function testGetArgsOfFunctionWithoutParameters(): void
    {
        $generator = new FunctionCallArgumentListGenerator(new ReflectionFunction('time'));

        $this->assertSame('', $generator->generate());
        $this->assertSame([], $generator->getArgs());
    }

    /**
     * Provides list of functions with expected generated code for calling such functions
     *
     * @return array<array{string, string}>
     */
    public static function dataGenerator(): array
    {
        return [
            ['var_dump', '\array_slice([$value], 0, \func_num_args()), $values'],                    // var_dump(...$vars)
            ['array_pop', '[&$array]'],               // array_pop(&$stack)
            ['array_diff_assoc', '\array_slice([$array], 0, \func_num_args()), $arrays'], // array_diff_assoc($arr1, array ...$arrays)
            ['strcoll', '[$string1, $string2]'],            // strcoll($string1, $string2)
            ['basename', '\array_slice([$path, $suffix], 0, \func_num_args())'],  // basename($path, $suffix = null)
        ];
    }
}
