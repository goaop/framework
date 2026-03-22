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

use Go\Stubs\First;
use PHPUnit\Framework\TestCase;

use ReflectionMethod;

use function preg_replace;

/**
 * Test case for generated method definition
 */
class InterceptedMethodGeneratorTest extends TestCase
{
    /**
     * Tests that generator can generate valid method definition
     *
     * @throws \ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerate(string $className, string $methodName, string $expectedSignature): void
    {
        $reflectionMethod = new ReflectionMethod($className, $methodName);
        $generator        = new InterceptedMethodGenerator($reflectionMethod, '');

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
            'variadicArgsTest' => [
                First::class,
                'variadicArgsTest',
                'public function variadicArgsTest(...$args): string'
            ],
            'staticLsbRecursion' => [
                First::class,
                'staticLsbRecursion',
                'public static function staticLsbRecursion(int $value, int $level = 0): int'
            ],
            'staticLsbProtected' => [
                First::class,
                'staticLsbProtected',
                'protected static function staticLsbProtected(): string'
            ],
            'passByReference' => [
                First::class,
                'passByReference',
                'public function passByReference(&$valueByReference)'
            ],
            'privateMethod' => [
                First::class,
                'privateMethod',
                'private function privateMethod(): int'
            ],
            'publicMethodWithUnionTypeReturn' => [
                First::class,
                'publicMethodWithUnionTypeReturn',
                'public function publicMethodWithUnionTypeReturn(\Exception|\Closure $value): \Exception|\Closure'
            ],
            'publicMethodWithIntersectionTypeReturn' => [
                First::class,
                'publicMethodWithIntersectionTypeReturn',
                'public function publicMethodWithIntersectionTypeReturn(\Exception&\Countable $value): \Exception&\Countable'
            ],
            'publicMethodWithDNFTypeReturn' => [
                First::class,
                'publicMethodWithDNFTypeReturn',
                'public function publicMethodWithDNFTypeReturn(\Iterator|(\Exception&\Countable) $value): \Iterator|(\Exception&\Countable)'
            ],
            'publicMethodWithAttribute' => [
                First::class,
                'publicMethodWithAttribute',
                "public function publicMethodWithAttribute(\n    #[\\Go\\Stubs\\StubAttribute('argument')]\n    string \$argument\n): string"
            ],
        ];
    }
}
