<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use Exception;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;

/**
 * Test functions for FunctionGenerator.
 */
function funcGenHelper_simple(string $name, int $count = 0): string
{
    return str_repeat($name, $count);
}

function funcGenHelper_nullable(?string $x = null): ?string
{
    return $x;
}

function funcGenHelper_variadic(string ...$items): array
{
    return $items;
}

function funcGenHelper_void(): void {}

function funcGenHelper_classReturn(): Exception
{
    return new Exception();
}

class FunctionGeneratorTest extends TestCase
{
    public function testFromReflectionSimple(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $output = $gen->generate();
        $this->assertStringContainsString('function funcGenHelper_simple', $output);
        $this->assertStringContainsString('string $name', $output);
        $this->assertStringContainsString('int $count = 0', $output);
        $this->assertStringContainsString(': string', $output);
    }

    public function testFromReflectionNullable(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_nullable'));
        $output = $gen->generate();
        $this->assertStringContainsString('?string $x = null', $output);
        $this->assertStringContainsString(': ?string', $output);
    }

    public function testFromReflectionVariadic(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_variadic'));
        $output = $gen->generate();
        $this->assertStringContainsString('string ...$items', $output);
    }

    public function testFromReflectionVoidReturn(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_void'));
        $output = $gen->generate();
        $this->assertStringContainsString(': void', $output);
    }

    public function testFromReflectionClassReturn(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_classReturn'));
        $output = $gen->generate();
        $this->assertStringContainsString(': \Exception', $output);
    }

    public function testSetAndGetBody(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setBody("return 'hello';");
        $this->assertStringContainsString("return 'hello'", $gen->getBody());
    }

    public function testSetAndGetStmts(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setBody("return 'x';");
        $stmts = $gen->getStmts();
        $this->assertNotNull($stmts);
        $this->assertNotEmpty($stmts);
    }

    public function testSetStmtsFromArray(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setBody("return 'original';");
        $stmts = $gen->getStmts();

        $gen2 = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen2->setStmts($stmts);
        $this->assertStringContainsString("return 'original'", $gen2->getBody());
    }

    public function testGetNode(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $node = $gen->getNode();
        $this->assertInstanceOf(Function_::class, $node);
        $this->assertSame('funcGenHelper_simple', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $this->assertSame('funcGenHelper_simple', $gen->getName());
    }

    public function testSetDocBlock(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setDocBlock(new DocBlockGenerator('My function.'));
        $output = $gen->generate();
        $this->assertStringContainsString('My function.', $output);
    }

    public function testGetBodyEmptyWhenNoStmts(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $this->assertSame('', $gen->getBody());
    }

    public function testAddParameter(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_void'));
        $param = new ParameterGenerator('extra', TypeGenerator::fromTypeString('bool'));
        $gen->addParameter($param);
        $output = $gen->generate();
        $this->assertStringContainsString('bool $extra', $output);
    }

    public function testSetReturnsReference(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setReturnsReference(true);
        $output = $gen->generate();
        $this->assertStringContainsString('function &funcGenHelper_simple', $output);
    }

    public function testWideningMode(): void
    {
        $gen = FunctionGenerator::fromReflection(
            new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'),
            true
        );
        $output = $gen->generate();
        $this->assertStringNotContainsString('string $name', $output);
        $this->assertStringContainsString('$name', $output);
    }

    public function testSetBodyEmptyString(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_simple'));
        $gen->setBody('');
        $stmts = $gen->getStmts();
        $this->assertIsArray($stmts);
        $this->assertEmpty($stmts);
    }

    public function testSetReturnTypeFromTypeGenerator(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(__NAMESPACE__ . '\funcGenHelper_void'));
        $typeGen = TypeGenerator::fromTypeString('int');
        $gen->setReturnType($typeGen);
        $output = $gen->generate();
        $this->assertStringContainsString(': int', $output);
    }

    public function testManualConstructor(): void
    {
        $gen = new FunctionGenerator('myFunc');
        $gen->setBody('return 42;');
        $output = $gen->generate();
        $this->assertStringContainsString('function myFunc', $output);
        $this->assertStringContainsString('return 42', $output);
    }
}
