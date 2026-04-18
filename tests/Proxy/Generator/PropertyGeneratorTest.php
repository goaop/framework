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
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;

class PropertyGeneratorTest extends TestCase
{
    public function testBasicPublicProperty(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $output = $gen->generate();
        $this->assertStringContainsString('public $myProp', $output);
    }

    public function testProtectedProperty(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PROTECTED);
        $output = $gen->generate();
        $this->assertStringContainsString('protected $myProp', $output);
    }

    public function testPrivateProperty(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PRIVATE);
        $output = $gen->generate();
        $this->assertStringContainsString('private $myProp', $output);
    }

    public function testStaticProperty(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);
        $output = $gen->generate();
        $this->assertStringContainsString('static', $output);
        $this->assertStringContainsString('private', $output);
    }

    public function testPropertyWithDefaultValue(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PRIVATE);
        $gen->setDefaultValue([]);
        $output = $gen->generate();
        $this->assertStringContainsString('= []', $output);
    }

    public function testPropertyWithStringDefault(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $gen->setDefaultValue('hello');
        $output = $gen->generate();
        $this->assertStringContainsString("= 'hello'", $output);
    }

    public function testPropertyWithNullDefault(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $gen->setDefaultValue(null);
        $gen->setType(TypeGenerator::fromTypeString('?string'));
        $output = $gen->generate();
        $this->assertStringContainsString('?string', $output);
        $this->assertStringContainsString('= null', $output);
    }

    public function testSetType(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);
        $gen->setType(TypeGenerator::fromTypeString('array'));
        $output = $gen->generate();
        $this->assertStringContainsString('array', $output);
    }

    public function testSetDocBlock(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PRIVATE);
        $gen->setDocBlock(new DocBlockGenerator('My prop doc.'));
        $output = $gen->generate();
        $this->assertStringContainsString('My prop doc.', $output);
    }

    public function testGetNode(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $node = $gen->getNode();
        $this->assertInstanceOf(Property::class, $node);
        $this->assertSame('myProp', (string) $node->props[0]->name);
    }

    public function testGetName(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $this->assertSame('myProp', $gen->getName());
    }

    public function testImplementsPropertyNodeProvider(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $this->assertInstanceOf(PropertyNodeProvider::class, $gen);
    }

    public function testAddAttributeGroups(): void
    {
        $factory = new BuilderFactory();
        $attrGroup = new AttributeGroup([$factory->attribute(new Name\FullyQualified('Deprecated'))]);
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $gen->addAttributeGroups([$attrGroup]);
        $output = $gen->generate();
        $this->assertStringContainsString('#[', $output);
        $this->assertStringContainsString('Deprecated', $output);
    }

    public function testAddAttributeGroupsEmpty(): void
    {
        $gen = new PropertyGenerator('myProp', PropertyGenerator::FLAG_PUBLIC);
        $gen->addAttributeGroups([]);
        $output = $gen->generate();
        $this->assertStringNotContainsString('#[', $output);
    }
}
