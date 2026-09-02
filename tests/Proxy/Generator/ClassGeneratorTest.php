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

use Go\Stubs\Generator\MethodGeneratorTestStub;
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
        $gen = new ClassGenerator('MyClass', 'My\Namespace', [], null);
        $output = $gen->generate();
        $this->assertStringContainsString('class MyClass', $output);
        $this->assertStringContainsString('namespace My\Namespace', $output);
    }

    public function testFinalClass(): void
    {
        $gen = new ClassGenerator('MyClass', null, [ClassModifier::FINAL], null);
        $output = $gen->generate();
        $this->assertStringContainsString('final class MyClass', $output);
    }

    public function testAbstractClass(): void
    {
        $gen = new ClassGenerator('MyClass', null, [ClassModifier::ABSTRACT], null);
        $output = $gen->generate();
        $this->assertStringContainsString('abstract class MyClass', $output);
    }

    public function testExtendsSimpleName(): void
    {
        // Parent without namespace separator — should NOT be FQN
        $gen = new ClassGenerator('MyClass', 'Foo', [], 'ParentClass');
        $output = $gen->generate();
        $this->assertStringContainsString('extends ParentClass', $output);
    }

    public function testExtendsFullyQualified(): void
    {
        // Parent with namespace separator — should be FQN
        $gen = new ClassGenerator('MyClass', 'Foo', [], 'Other\Namespace\ParentClass');
        $output = $gen->generate();
        $this->assertStringContainsString('extends \Other\Namespace\ParentClass', $output);
    }

    public function testImplementsInterface(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null, ['\Countable']);
        $output = $gen->generate();
        $this->assertStringContainsString('implements', $output);
        $this->assertStringContainsString('Countable', $output);
    }

    public function testImplementsMultipleInterfaces(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null, ['\Countable', '\Iterator']);
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
        $this->assertStringContainsString('Iterator', $output);
    }

    public function testImplementsGlobalInterfaceIsFullyQualifiedInNamespace(): void
    {
        // A global-namespace interface (single segment) must not resolve
        // relative to the generated class namespace
        $gen = new ClassGenerator('MyClass', 'My\Namespace', [], null, ['\Stringable']);
        $output = $gen->generate();
        $this->assertStringContainsString('implements \Stringable', $output);
    }

    public function testExtendsExplicitlyRootedGlobalParentStaysFullyQualified(): void
    {
        $gen = new ClassGenerator('MyClass', 'My\Namespace', [], '\Exception');
        $output = $gen->generate();
        $this->assertStringContainsString('extends \Exception', $output);
    }

    public function testUsesExplicitlyRootedGlobalTraitStaysFullyQualified(): void
    {
        $gen = new ClassGenerator('MyClass', 'My\Namespace', [], null);
        $gen->addTraits(['\GlobalHelperTrait', 'MyClass__AopProxied']);
        $output = $gen->generate();
        $this->assertStringContainsString('\GlobalHelperTrait', $output);
        // Deliberate short names keep referring to the class' own namespace
        $this->assertStringContainsString('MyClass__AopProxied', $output);
        $this->assertStringNotContainsString('\MyClass__AopProxied', $output);
    }

    public function testWithMethod(): void
    {
        $method = MethodGenerator::fromReflection(new ReflectionMethod(
            MethodGeneratorTestStub::class,
            'publicMethod'
        ));
        $gen = new ClassGenerator('MyClass', null, [], null, [], [], [$method]);
        $output = $gen->generate();
        $this->assertStringContainsString('function publicMethod', $output);
    }

    public function testWithProperty(): void
    {
        $prop = new PropertyGenerator('myProp', [PropertyModifier::PRIVATE, PropertyModifier::STATIC]);
        $prop->defaultValue = [];
        $prop->type = TypeGenerator::fromTypeString('array');
        $gen = new ClassGenerator('MyClass', null, [], null, [], [$prop]);
        $output = $gen->generate();
        $this->assertStringContainsString('$myProp', $output);
    }

    public function testAddUse(): void
    {
        $gen = new ClassGenerator('MyClass', 'Foo', [], null);
        $gen->addUse(\Exception::class);
        $output = $gen->generate();
        $this->assertStringContainsString('use Exception', $output);
    }

    public function testAddUseWithAlias(): void
    {
        $gen = new ClassGenerator('MyClass', 'Foo', [], null);
        $gen->addUse(\Exception::class, 'Ex');
        $output = $gen->generate();
        $this->assertStringContainsString('use Exception as Ex', $output);
    }

    public function testAddTraits(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $gen->addTraits(['\My\Trait\Foo']);
        $output = $gen->generate();
        $this->assertStringContainsString('use \My\Trait\Foo', $output);
    }

    public function testSetDocBlock(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $gen->docBlock = new DocBlockGenerator('Class doc.');
        $output = $gen->generate();
        $this->assertStringContainsString('Class doc.', $output);
    }

    public function testGetNode(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $node = $gen->getNode();
        $this->assertSame('MyClass', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $this->assertSame('MyClass', $gen->name);
    }

    public function testGetNodeDoesNotIncludeNamespace(): void
    {
        $gen = new ClassGenerator('MyClass', 'My\NS', [], null);
        $node = $gen->getNode();
        // getNode() should return just the class node, not wrapped in namespace
        $this->assertSame('MyClass', (string) $node->name);
    }

    public function testEmptyTraitSkipped(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $gen->addTraits(['', 'ValidTrait']);
        $output = $gen->generate();
        $this->assertStringContainsString('ValidTrait', $output);
    }

    public function testImplementsSkipsEmpty(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null, ['', '\Countable']);
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
    }

    public function testAddAttributeGroups(): void
    {
        $factory = new BuilderFactory();
        $attrGroup = new AttributeGroup([$factory->attribute(new Name\FullyQualified('Deprecated'))]);
        $gen = new ClassGenerator('MyClass', null, [], null);
        $gen->attrGroups = [$attrGroup];
        $output = $gen->generate();
        $this->assertStringContainsString('#[', $output);
        $this->assertStringContainsString('Deprecated', $output);
    }

    public function testAddAttributeGroupsEmpty(): void
    {
        $gen = new ClassGenerator('MyClass', null, [], null);
        $gen->attrGroups = [];
        $output = $gen->generate();
        $this->assertStringNotContainsString('#[', $output);
    }
}
