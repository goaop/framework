<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Generator;

use PHPUnit\Framework\TestCase;
use PhpParser\Node\Stmt\Function_;
use ReflectionFunction;

class FunctionGeneratorTest extends TestCase
{
    private const STUBS_NS = 'Go\Proxy\Generator\Stubs';

    public function testFromReflectionSimple(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $output = $gen->generate();
        $this->assertStringContainsString('function funcGenHelper_simple', $output);
        $this->assertStringContainsString('string $name', $output);
        $this->assertStringContainsString('int $count = 0', $output);
        $this->assertStringContainsString(': string', $output);
    }

    public function testFromReflectionNullable(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_nullable'));
        $output = $gen->generate();
        $this->assertStringContainsString('?string $x = null', $output);
        $this->assertStringContainsString(': ?string', $output);
    }

    public function testFromReflectionVariadic(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_variadic'));
        $output = $gen->generate();
        $this->assertStringContainsString('string ...$items', $output);
    }

    public function testFromReflectionVoidReturn(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_void'));
        $output = $gen->generate();
        $this->assertStringContainsString(': void', $output);
    }

    public function testFromReflectionClassReturn(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_classReturn'));
        $output = $gen->generate();
        $this->assertStringContainsString(': \Exception', $output);
    }

    public function testSetAndGetBody(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->body = "return 'hello';";        $this->assertStringContainsString("return 'hello'", $gen->body);
    }

    public function testSetAndGetStmts(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->body = "return 'x';";        $stmts = $gen->stmts;
        $this->assertNotNull($stmts);
        $this->assertNotEmpty($stmts);
    }

    public function testSetStmtsFromArray(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->body = "return 'original';";        $stmts = $gen->stmts;

        $gen2 = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen2->stmts = $stmts;
        $this->assertStringContainsString("return 'original'", $gen2->body);
    }

    public function testGetNode(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $node = $gen->getNode();
        $this->assertInstanceOf(Function_::class, $node);
        $this->assertSame('funcGenHelper_simple', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $this->assertSame('funcGenHelper_simple', $gen->name);
    }

    public function testSetDocBlock(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->docBlock = new DocBlockGenerator('My function.');
        $output = $gen->generate();
        $this->assertStringContainsString('My function.', $output);
    }

    public function testGetBodyEmptyWhenNoStmts(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $this->assertSame('', $gen->body);
    }

    public function testAddParameter(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_void'));
        $param = new ParameterGenerator('extra', TypeGenerator::fromTypeString('bool'));
        $gen->addParameter($param);
        $output = $gen->generate();
        $this->assertStringContainsString('bool $extra', $output);
    }

    public function testSetReturnsReference(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->returnsRef = true;
        $output = $gen->generate();
        $this->assertStringContainsString('function &funcGenHelper_simple', $output);
    }

    public function testSetBodyEmptyString(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_simple'));
        $gen->body = '';
        $stmts = $gen->stmts;
        $this->assertIsArray($stmts);
        $this->assertEmpty($stmts);
    }

    public function testSetReturnTypeFromTypeGenerator(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_void'));
        $typeGen = TypeGenerator::fromTypeString('int');
        $gen->returnType = $typeGen;
        $output = $gen->generate();
        $this->assertStringContainsString(': int', $output);
    }

    public function testManualConstructor(): void
    {
        $gen = new FunctionGenerator('myFunc');
        $gen->body = 'return 42;';        $output = $gen->generate();
        $this->assertStringContainsString('function myFunc', $output);
        $this->assertStringContainsString('return 42', $output);
    }

    public function testFromReflectionPreservesFunctionAttribute(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_deprecated'));
        $output = $gen->generate();
        $this->assertStringContainsString('Deprecated', $output);
        $this->assertStringContainsString('#[', $output);
    }

    public function testFromReflectionNoAttributeWhenNone(): void
    {
        $gen = FunctionGenerator::fromReflection(new ReflectionFunction(self::STUBS_NS . '\funcGenHelper_noAttr'));
        $output = $gen->generate();
        $this->assertStringNotContainsString('#[', $output);
    }
}
