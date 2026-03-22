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

use PHPUnit\Framework\TestCase;
use PhpParser\BuilderFactory;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use ReflectionMethod;

class ClassGeneratorTest extends TestCase
{
    public function testBasicClass(): void
    {
        $gen = new ClassGenerator('MyClass', 'My\Namespace', null, null);
        $output = $gen->generate();
        $this->assertStringContainsString('class MyClass', $output);
        $this->assertStringContainsString('namespace My\Namespace', $output);
    }

    public function testFinalClass(): void
    {
        $gen = new ClassGenerator('MyClass', null, ClassGenerator::FLAG_FINAL, null);
        $output = $gen->generate();
        $this->assertStringContainsString('final class MyClass', $output);
    }

    public function testAbstractClass(): void
    {
        $gen = new ClassGenerator('MyClass', null, ClassGenerator::FLAG_ABSTRACT, null);
        $output = $gen->generate();
        $this->assertStringContainsString('abstract class MyClass', $output);
    }

    public function testExtendsSimpleName(): void
    {
        // Parent without namespace separator — should NOT be FQN
        $gen = new ClassGenerator('MyClass', 'Foo', null, 'ParentClass');
        $output = $gen->generate();
        $this->assertStringContainsString('extends ParentClass', $output);
    }

    public function testExtendsFullyQualified(): void
    {
        // Parent with namespace separator — should be FQN
        $gen = new ClassGenerator('MyClass', 'Foo', null, 'Other\Namespace\ParentClass');
        $output = $gen->generate();
        $this->assertStringContainsString('extends \Other\Namespace\ParentClass', $output);
    }

    public function testImplementsInterface(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null, ['\Countable']);
        $output = $gen->generate();
        $this->assertStringContainsString('implements', $output);
        $this->assertStringContainsString('Countable', $output);
    }

    public function testImplementsMultipleInterfaces(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null, ['\Countable', '\Iterator']);
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
        $this->assertStringContainsString('Iterator', $output);
    }

    public function testWithMethod(): void
    {
        $method = MethodGenerator::fromReflection(new ReflectionMethod(
            MethodGeneratorTestStub::class,
            'publicMethod'
        ));
        $gen = new ClassGenerator('MyClass', null, null, null, [], [], [$method]);
        $output = $gen->generate();
        $this->assertStringContainsString('function publicMethod', $output);
    }

    public function testWithProperty(): void
    {
        $prop = new PropertyGenerator('myProp', [], PropertyGenerator::FLAG_PRIVATE | PropertyGenerator::FLAG_STATIC);
        $prop->setType(TypeGenerator::fromTypeString('array'));
        $gen = new ClassGenerator('MyClass', null, null, null, [], [$prop]);
        $output = $gen->generate();
        $this->assertStringContainsString('$myProp', $output);
    }

    public function testAddUse(): void
    {
        $gen = new ClassGenerator('MyClass', 'Foo', null, null);
        $gen->addUse(\Exception::class);
        $output = $gen->generate();
        $this->assertStringContainsString('use Exception', $output);
    }

    public function testAddUseWithAlias(): void
    {
        $gen = new ClassGenerator('MyClass', 'Foo', null, null);
        $gen->addUse(\Exception::class, 'Ex');
        $output = $gen->generate();
        $this->assertStringContainsString('use Exception as Ex', $output);
    }

    public function testAddTraits(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $gen->addTraits(['\My\Trait\Foo']);
        $output = $gen->generate();
        $this->assertStringContainsString('use \My\Trait\Foo', $output);
    }

    public function testSetDocBlock(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $gen->setDocBlock(new DocBlockGenerator('Class doc.'));
        $output = $gen->generate();
        $this->assertStringContainsString('Class doc.', $output);
    }

    public function testGetNode(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $node = $gen->getNode();
        $this->assertInstanceOf(Class_::class, $node);
        $this->assertSame('MyClass', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $this->assertSame('MyClass', $gen->getName());
    }

    public function testGetNodeDoesNotIncludeNamespace(): void
    {
        $gen = new ClassGenerator('MyClass', 'My\NS', null, null);
        $node = $gen->getNode();
        // getNode() should return just the class node, not wrapped in namespace
        $this->assertInstanceOf(Class_::class, $node);
    }

    public function testEmptyTraitSkipped(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $gen->addTraits(['', 'ValidTrait']);
        $output = $gen->generate();
        $this->assertStringContainsString('ValidTrait', $output);
    }

    public function testImplementsSkipsEmpty(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null, ['', '\Countable']);
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
    }

    public function testAddAttributeGroups(): void
    {
        $factory = new BuilderFactory();
        $attrGroup = new AttributeGroup([$factory->attribute(new Name\FullyQualified('Deprecated'))]);
        $gen = new ClassGenerator('MyClass', null, null, null);
        $gen->addAttributeGroups([$attrGroup]);
        $output = $gen->generate();
        $this->assertStringContainsString('#[', $output);
        $this->assertStringContainsString('Deprecated', $output);
    }

    public function testAddAttributeGroupsEmpty(): void
    {
        $gen = new ClassGenerator('MyClass', null, null, null);
        $gen->addAttributeGroups([]);
        $output = $gen->generate();
        $this->assertStringNotContainsString('#[', $output);
    }
}
