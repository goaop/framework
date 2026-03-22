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
use PhpParser\Node;
use ReflectionFunction;
use ReflectionParameter;

/**
 * Helper functions for parameter reflection tests.
 */
function paramGenHelper_simple(string $name, int $count = 0): void {}
function paramGenHelper_nullable(?string $name = null): void {}
function paramGenHelper_byRef(array &$data): void {}
function paramGenHelper_variadic(string ...$items): void {}
function paramGenHelper_variadicByRef(int &...$nums): void {}
function paramGenHelper_classType(Exception $ex): void {}
function paramGenHelper_noType($x): void {}

class ParameterGeneratorTest extends TestCase
{
    private function getParam(string $func, int $idx = 0): ReflectionParameter
    {
        return (new ReflectionFunction($func))->getParameters()[$idx];
    }

    public function testFromReflectionSimple(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_simple', 0));
        $output = $gen->generate();
        $this->assertSame('string $name', $output);
    }

    public function testFromReflectionWithDefault(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_simple', 1));
        $output = $gen->generate();
        $this->assertSame('int $count = 0', $output);
    }

    public function testFromReflectionNullable(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_nullable', 0));
        $output = $gen->generate();
        $this->assertSame('?string $name = null', $output);
    }

    public function testFromReflectionByRef(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_byRef', 0));
        $output = $gen->generate();
        $this->assertSame('array &$data', $output);
    }

    public function testFromReflectionVariadic(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_variadic', 0));
        $output = $gen->generate();
        $this->assertSame('string ...$items', $output);
    }

    public function testFromReflectionVariadicByRef(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_variadicByRef', 0));
        $output = $gen->generate();
        $this->assertSame('int &...$nums', $output);
    }

    public function testFromReflectionClassType(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_classType', 0));
        $output = $gen->generate();
        $this->assertSame('\Exception $ex', $output);
    }

    public function testFromReflectionNoType(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_noType', 0));
        $output = $gen->generate();
        $this->assertSame('$x', $output);
    }

    public function testGetNodeReturnsParam(): void
    {
        $gen = ParameterGenerator::fromReflection($this->getParam(__NAMESPACE__ . '\paramGenHelper_simple', 0));
        $node = $gen->getNode();
        $this->assertInstanceOf(Node\Param::class, $node);
        $this->assertSame('name', (string) $node->var->name);
    }

    public function testConstructorManual(): void
    {
        $type = TypeGenerator::fromTypeString('int');
        $gen = new ParameterGenerator('myParam', $type, false, false, new ValueGenerator(99));
        $this->assertSame('int $myParam = 99', $gen->generate());
        $this->assertSame('myParam', $gen->getName());
        $this->assertSame($type, $gen->getType());
        $this->assertFalse($gen->getPassedByReference());
        $this->assertFalse($gen->getVariadic());
        $this->assertNotNull($gen->getDefaultValue());
    }

    public function testSetDefaultValue(): void
    {
        $gen = new ParameterGenerator('myParam', TypeGenerator::fromTypeString('string'));
        $gen->setDefaultValue(new ValueGenerator('hello'));
        $output = $gen->generate();
        $this->assertSame("string \$myParam = 'hello'", $output);
    }

    public function testWideningModeDropsTypeForBuiltin(): void
    {
        // When useWidening=true, builtin-typed params lose their type constraint
        $gen = ParameterGenerator::fromReflection(
            $this->getParam(__NAMESPACE__ . '\paramGenHelper_simple', 0),
            true
        );
        $output = $gen->generate();
        // With widening, the type should be removed
        $this->assertSame('$name', $output);
    }
}
