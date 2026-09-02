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

use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\Generator\Stubs\AttrGenHelperClass;
use Go\Proxy\Generator\Stubs\AttrGenRichHelperClass;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\AttributeGroup;
use PhpParser\PrettyPrinter\Standard;
use ReflectionFunction;
use ReflectionMethod;

class AttributeGroupsGeneratorTest extends TestCase
{
    private const STUBS_NS = 'Go\Proxy\Generator\Stubs';

    private static Standard $printer;

    public static function setUpBeforeClass(): void
    {
        self::$printer = new Standard(['shortArraySyntax' => true]);
    }

    /**
     * @param array<\PhpParser\Node\AttributeGroup> $groups
     */
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
        $func = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_noArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestNoArgsAttr', $output);
    }

    public function testAttributeWithPositionalArgs(): void
    {
        $func = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_withArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('TestArgsAttr', $output);
        $this->assertStringContainsString("'hello'", $output);
        $this->assertStringContainsString('3', $output);
    }

    public function testAttributeWithNamedArgs(): void
    {
        $func = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_namedArgs');
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
        $func = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_multipleAttrs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(2, $groups);
    }

    public function testAttributeNameIsFQN(): void
    {
        $func = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_noArgs');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $output = $this->generateGroups($groups);
        // Must be fully-qualified name (starts with backslash)
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestNoArgsAttr', $output);
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

    /**
     * Native reflection path: enum case arguments are representable as PHP code
     * and must be emitted as fully-qualified class constant fetches (issue #601).
     */
    public function testNativeReflectionEnumCaseArgument(): void
    {
        $func   = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_enumArg');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestStatusEnum::Active', $output);
    }

    /**
     * Native reflection path: attribute arguments without a PHP-code representation
     * (objects from new-in-initializer) are skipped best-effort instead of aborting
     * the whole proxy generation with a LogicException (issue #601).
     */
    public function testNativeReflectionSkipsUnrepresentableObjectArgument(): void
    {
        $func   = new ReflectionFunction(self::STUBS_NS . '\attrGenHelper_objectArg');
        $groups = AttributeGroupsGenerator::fromReflectionAttributes($func->getAttributes());
        $this->assertSame([], $groups);
    }

    /**
     * AST path (fromReflector on parser-reflection): enum case arguments are cloned
     * from the source expression with the enum name fully qualified (issue #601).
     */
    public function testAstPathPreservesEnumCaseArgument(): void
    {
        $func   = $this->getParserReflectionNamespace()->getFunction('attrGenHelper_enumArg');
        $groups = AttributeGroupsGenerator::fromReflector($func);
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestRichAttr', $output);
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestStatusEnum::Active', $output);
    }

    /**
     * AST path: new-in-initializer arguments are preserved as expressions instead of
     * crashing on the evaluated object value (issue #601).
     */
    public function testAstPathPreservesNewInInitializerArgument(): void
    {
        $func   = $this->getParserReflectionNamespace()->getFunction('attrGenHelper_objectArg');
        $groups = AttributeGroupsGenerator::fromReflector($func);
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('new \ArrayObject([1, 2])', $output);
    }

    /**
     * AST path: global constants in attribute arguments are preserved as constant
     * fetches — never evaluated (which crashes parser-reflection for global constants
     * in namespaced files) and never constant-folded (issue #602).
     */
    public function testAstPathPreservesGlobalConstantArgument(): void
    {
        $func   = $this->getParserReflectionNamespace()->getFunction('attrGenHelper_globalConstArg');
        $groups = AttributeGroupsGenerator::fromReflector($func);
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('PHP_INT_MAX', $output);
        $this->assertStringNotContainsString((string) PHP_INT_MAX, $output);
    }

    /**
     * AST path via MethodGenerator: a method annotated with a global constant argument
     * must generate without evaluating the attribute arguments (issue #602 crashed
     * here with "Namespace  was not found in the file" during weaving).
     */
    public function testMethodGeneratorWithGlobalConstantAttributeArgument(): void
    {
        $method = $this->getParserReflectionNamespace()
            ->getClass(AttrGenRichHelperClass::class)
            ->getMethod('limited');
        $gen    = MethodGenerator::fromReflection($method);
        $output = $gen->generate();
        $this->assertStringContainsString('#[\\' . self::STUBS_NS . '\\TestRichAttr(PHP_INT_MAX)]', $output);
    }

    /**
     * AST path via MethodGenerator/ParameterGenerator: enum case arguments on methods
     * and parameters are preserved as class constant fetches (issue #601).
     */
    public function testMethodGeneratorWithEnumCaseAttributeArguments(): void
    {
        $method = $this->getParserReflectionNamespace()
            ->getClass(AttrGenRichHelperClass::class)
            ->getMethod('tagged');
        $gen    = MethodGenerator::fromReflection($method);
        $output = $gen->generate();
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestStatusEnum::Disabled', $output);
        $this->assertStringContainsString('123', $output);
        // Parameter attribute
        $this->assertStringContainsString('\\' . self::STUBS_NS . '\\TestStatusEnum::Active', $output);
    }

    /**
     * AST path: PHP 8.5 first-class callable syntax in attribute arguments (issue #601).
     */
    #[RequiresPhp('>= 8.5.0')]
    public function testAstPathPreservesFccArgument(): void
    {
        require_once __DIR__ . '/Stubs/AttributeGroupsGenerator85Stubs.php';
        $func   = $this->getParserReflectionNamespace(__DIR__ . '/Stubs/AttributeGroupsGenerator85Stubs.php')
            ->getFunction('attrGenHelper85_fccArg');
        $groups = AttributeGroupsGenerator::fromReflector($func);
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('strlen(...)', $output);
    }

    /**
     * AST path: PHP 8.5 closures in attribute arguments (issue #601).
     */
    #[RequiresPhp('>= 8.5.0')]
    public function testAstPathPreservesClosureArgument(): void
    {
        require_once __DIR__ . '/Stubs/AttributeGroupsGenerator85Stubs.php';
        $func   = $this->getParserReflectionNamespace(__DIR__ . '/Stubs/AttributeGroupsGenerator85Stubs.php')
            ->getFunction('attrGenHelper85_closureArg');
        $groups = AttributeGroupsGenerator::fromReflector($func);
        $this->assertCount(1, $groups);
        $output = $this->generateGroups($groups);
        $this->assertStringContainsString('static function (int $x) : int', str_replace('):', ') :', $output));
        $this->assertStringContainsString('$x * 2', $output);
    }

    /**
     * Returns the stubs namespace parsed through parser-reflection (with NameResolver),
     * so reflection objects expose their AST nodes via getNode().
     */
    private function getParserReflectionNamespace(?string $file = null): ReflectionFileNamespace
    {
        return new ReflectionFileNamespace(
            $file ?? __DIR__ . '/Stubs/AttributeGroupsGeneratorStubs.php',
            self::STUBS_NS
        );
    }
}
