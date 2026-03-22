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

use Attribute;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\AttributeGroup;
use PhpParser\PrettyPrinter\Standard;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Test attribute classes for AttributeGroupsGeneratorTest.
 */
#[Attribute(Attribute::TARGET_ALL)]
class TestNoArgsAttr {}

#[Attribute(Attribute::TARGET_ALL)]
class TestArgsAttr
{
    public function __construct(
        public string $value,
        public int $count = 1,
    ) {}
}

#[Attribute(Attribute::TARGET_ALL)]
class TestNamedArgsAttr
{
    public function __construct(
        public string $label = '',
        public bool $enabled = true,
    ) {}
}

/**
 * Helper functions for reflection-based attribute tests.
 */
#[TestNoArgsAttr]
function attrGenHelper_noArgs(): void {}

#[TestArgsAttr('hello', 3)]
function attrGenHelper_withArgs(): void {}

#[TestNamedArgsAttr(label: 'test', enabled: false)]
function attrGenHelper_namedArgs(): void {}

#[TestNoArgsAttr]
#[TestArgsAttr('multi')]
function attrGenHelper_multipleAttrs(): void {}

/**
 * Helper class for class-level attribute tests.
 */
#[TestNoArgsAttr]
class AttrGenHelperClass
{
    #[TestNoArgsAttr]
    public string $annotatedProp = '';

    #[TestArgsAttr('method_value')]
    public function annotatedMethod(): void {}

    public function methodWithAttrParam(#[TestNoArgsAttr] string $name): void {}
}

class AttributeGroupsGeneratorTest extends TestCase
{
    private static Standard $printer;

    public static function setUpBeforeClass(): void
    {
        self::$printer = new Standard(['shortArraySyntax' => true]);
    }

    private function generateGroups(array $groups): string
    {
        $output = '';
        foreach ($groups as $group) {
            $output .= self::$printer->prettyPrint([$group]) . "\n";
        }
        return $output;
    }

    public function testEmptyReturnsEmptyArray(): void
    {
        $groups = AttributeGroupsGenerator::fromReflectionAttributes([]);
        $this->assertSame([], $groups);
    }

    public function testSingleAttributeNoArgs(): void
    {
        $func = new ReflectionFunction(__NAMESPACE__ . '\attrGenHelper_noArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $this->assertInstanceOf(AttributeGroup::class, $groups[0]);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestNoArgsAttr', $output);
    }

    public function testAttributeWithPositionalArgs(): void
    {
        $func = new ReflectionFunction(__NAMESPACE__ . '\attrGenHelper_withArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestArgsAttr', $output);
        $this->assertStringContainsString("'hello'", $output);
        $this->assertStringContainsString('3', $output);
    }

    public function testAttributeWithNamedArgs(): void
    {
        $func = new ReflectionFunction(__NAMESPACE__ . '\attrGenHelper_namedArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestNamedArgsAttr', $output);
        $this->assertStringContainsString('label', $output);
        $this->assertStringContainsString("'test'", $output);
        $this->assertStringContainsString('enabled', $output);
        $this->assertStringContainsString('false', $output);
    }

    public function testMultipleAttributesProduceMultipleGroups(): void
    {
        $func = new ReflectionFunction(__NAMESPACE__ . '\attrGenHelper_multipleAttrs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(2, $groups);
    }

    public function testAttributeNameIsFQN(): void
    {
        $func = new ReflectionFunction(__NAMESPACE__ . '\attrGenHelper_noArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $output = $this->generateGroups($groups);
        // Must be fully-qualified name (starts with backslash)
        $this->assertStringContainsString('\\' . __NAMESPACE__ . '\\TestNoArgsAttr', $output);
    }

    public function testMethodAttributes(): void
    {
        $method = new ReflectionMethod(AttrGenHelperClass::class, 'annotatedMethod');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($method->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestArgsAttr', $output);
        $this->assertStringContainsString("'method_value'", $output);
    }

    public function testParameterAttributes(): void
    {
        $method = new ReflectionMethod(AttrGenHelperClass::class, 'methodWithAttrParam');
        $params = $method->getParameters();
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($params[0]->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestNoArgsAttr', $output);
    }
}
