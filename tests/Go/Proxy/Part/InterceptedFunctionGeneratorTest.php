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

use Countable;
use Exception;
use Go\Proxy\Generator\FunctionGenerator;
use Go\Stubs\StubAttribute;
use Iterator;
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

#[StubAttribute("function")]
function funcWithAttributes(#[StubAttribute("argument")] string $argument): string
{
    return $argument;
}

function funcWithDNFTypeReturn(Iterator|(Exception&Countable) $value): Iterator|(Exception&Countable)
{
    return $value;
}

/**
 * Contains test function with nullable parameters and nullable return type
 */
function funcWithNullableParams(?string $name = null, int $count = 0): ?string
{
    return $name !== null ? str_repeat($name, $count) : null;
}

/**
 * Contains test function with standalone null return type (PHP 8.2+)
 */
function funcReturningNull(): null
{
    return null;
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
        $generator          = FunctionGenerator::fromReflection($reflectionFunction);
        $generator->setBody("\n");

        $generatedCode = $generator->generate();
        // Clean PhpDoc comment, @see https://stackoverflow.com/a/4207149/801258
        $generatedCode = preg_replace('#/\*.+?\*/#s', '', $generatedCode);
        // Remove trailing spaces and empty function body
        $generatedCode = trim($generatedCode, "\n{} ");
        $this->assertSame($expectedSignature, $generatedCode);
    }

    /**
     * Provides list of methods with expected attributes.
     * Signatures follow PhpParser PrettyPrinter format:
     *   - no space after `...` for variadic params
     *   - return type separated by `: ` (colon + one space, no leading space)
     */
    public static function dataGenerator(): array
    {
        return [
            'var_dump' => [
                'var_dump',
                'function var_dump(mixed $value, mixed ...$values): void'
            ],
            'array_pop' => [
                'array_pop',
                'function array_pop(array &$array): mixed'
            ],
            'strcoll' => [
                'strcoll',
                'function strcoll(string $string1, string $string2): int'
            ],
            'microtime' => [
                'microtime',
                'function microtime(bool $as_float = false): string|float'
            ],
            'funcWithReturnTypeAndDocBlock' => [
                '\Go\Proxy\Part\funcWithReturnTypeAndDocBlock',
                'function funcWithReturnTypeAndDocBlock(): \Exception'
            ],
            [
                'array_slice',
                'function array_slice(array $array, int $offset, ?int $length = null, bool $preserve_keys = false): array'
            ],
            [
                '\Go\Proxy\Part\funcWithNullableParams',
                'function funcWithNullableParams(?string $name = null, int $count = 0): ?string'
            ],
            [
                '\Go\Proxy\Part\funcReturningNull',
                'function funcReturningNull(): null'
            ],
            'funcWithAttributes' => [
                '\Go\Proxy\Part\funcWithAttributes',
                "#[\\Go\\Stubs\\StubAttribute('function')]\nfunction funcWithAttributes(\n    #[\\Go\\Stubs\\StubAttribute('argument')]\n    string \$argument\n): string"
            ],
            'funcWithDNFTypeReturn' => [
                '\Go\Proxy\Part\funcWithDNFTypeReturn',
                'function funcWithDNFTypeReturn(\Iterator|(\Exception&\Countable) $value): \Iterator|(\Exception&\Countable)'
            ],
        ];
    }
}
