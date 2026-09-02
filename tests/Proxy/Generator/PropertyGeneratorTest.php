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
use PhpParser\BuilderFactory;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PhpParser\ParserFactory;

class PropertyGeneratorTest extends TestCase
{
    public function testBasicPublicProperty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $output = $gen->generate();
        $this->assertStringContainsString('public $myProp', $output);
    }

    public function testProtectedProperty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PROTECTED]);
        $output = $gen->generate();
        $this->assertStringContainsString('protected $myProp', $output);
    }

    public function testPrivateProperty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE]);
        $output = $gen->generate();
        $this->assertStringContainsString('private $myProp', $output);
    }

    public function testStaticProperty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE, PropertyModifier::STATIC]);
        $output = $gen->generate();
        $this->assertStringContainsString('static', $output);
        $this->assertStringContainsString('private', $output);
    }

    public function testFinalProperty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC, PropertyModifier::FINAL]);
        $output = $gen->generate();
        $this->assertStringContainsString('final', $output);
        $this->assertStringContainsString('public', $output);
    }

    public function testPropertyWithDefaultValue(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE]);
        $gen->defaultValue = [];
        $output = $gen->generate();
        $this->assertStringContainsString('= []', $output);
    }

    public function testPropertyWithStringDefault(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $gen->defaultValue = 'hello';
        $output = $gen->generate();
        $this->assertStringContainsString("= 'hello'", $output);
    }

    public function testPropertyWithNullDefault(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $gen->defaultValue = null;
        $gen->type = TypeGenerator::fromTypeString('?string');
        $output = $gen->generate();
        $this->assertStringContainsString('?string', $output);
        $this->assertStringContainsString('= null', $output);
    }

    public function testSetType(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE, PropertyModifier::STATIC]);
        $gen->type = TypeGenerator::fromTypeString('array');
        $output = $gen->generate();
        $this->assertStringContainsString('array', $output);
    }

    public function testSetDocBlock(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE]);
        $gen->docBlock = new DocBlockGenerator('My prop doc.');
        $output = $gen->generate();
        $this->assertStringContainsString('My prop doc.', $output);
    }

    public function testGetNode(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $node = $gen->getNode();
        $this->assertSame('myProp', (string) $node->props[0]->name);
    }

    public function testGetName(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $this->assertSame('myProp', $gen->name);
    }

    public function testImplementsPropertyNodeProvider(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the implemented interface)
        $this->assertInstanceOf(PropertyNodeProvider::class, $gen);
    }

    public function testAddAttributeGroups(): void
    {
        $factory = new BuilderFactory();
        $attrGroup = new AttributeGroup([$factory->attribute(new Name\FullyQualified('Deprecated'))]);
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $gen->attrGroups = [$attrGroup];
        $output = $gen->generate();
        $this->assertStringContainsString('#[', $output);
        $this->assertStringContainsString('Deprecated', $output);
    }

    public function testAddAttributeGroupsEmpty(): void
    {
        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $gen->attrGroups = [];
        $output = $gen->generate();
        $this->assertStringNotContainsString('#[', $output);
    }

    public function testSetDefaultExpressionNode(): void
    {
        // Parse `\strlen(...);` to get a correctly-structured FCC FuncCall node
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts  = $parser->parse('<?php \strlen(...);');
        $this->assertNotNull($stmts, 'Failed to parse PHP snippet');
        $this->assertInstanceOf(\PhpParser\Node\Stmt\Expression::class, $stmts[0]);
        $exprNode = $stmts[0]->expr;

        $gen = new PropertyGenerator('myProp', [PropertyModifier::PUBLIC]);
        $gen->defaultExpressionNode = $exprNode;
        $gen->type = TypeGenerator::fromTypeString('callable');
        $output = $gen->generate();
        $this->assertStringContainsString('callable', $output);
        $this->assertStringContainsString('= \strlen(...)', $output);
    }
}
