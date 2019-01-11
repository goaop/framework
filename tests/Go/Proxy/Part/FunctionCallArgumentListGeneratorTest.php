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


class FunctionCallArgumentListGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Tests that generator can generate function call argument list
     *
     * @param string $functionName Function to reflect
     * @dataProvider dataGenerator

     * @throws \ReflectionException if function is not present
     */
    public function testGenerate(string $functionName, string $expectedLine)
    {
        $reflection = new \ReflectionFunction($functionName);
        $generator  = new FunctionCallArgumentListGenerator($reflection);
        $actualLine = $generator->generate();
        $this->assertSame($expectedLine, $actualLine);
    }

    /**
     * Provides list of functions with expected generated code for calling such functions
     *
     * @return array
     */
    public function dataGenerator(): array
    {
        return [
            ['var_dump', '$vars'],                  // var_dump(...$vars)
            ['array_pop', '[&$stack]'],             // array_pop(&$stack)
            ['array_push', '[&$stack], $vars'],     // array_push(&$stack, ...$vars)
            ['strcoll', '[$str1, $str2]'],          // strcoll($str1, $str2)
            ['basename', '\array_slice([$path, $suffix], 0, \func_num_args())'],  // basename($path, $suffix = null)
        ];
    }
}
