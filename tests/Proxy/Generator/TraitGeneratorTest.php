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
use PhpParser\Node\Stmt\Trait_;
use ReflectionMethod;

class TraitGeneratorTest extends TestCase
{
    public function testBasicTrait(): void
    {
        $gen = new TraitGenerator('MyTrait', 'My\Namespace');
        $output = $gen->generate();
        $this->assertStringContainsString('trait MyTrait', $output);
        $this->assertStringContainsString('namespace My\Namespace', $output);
    }

    public function testTraitWithoutNamespace(): void
    {
        $gen = new TraitGenerator('MyTrait', null);
        $output = $gen->generate();
        $this->assertStringContainsString('trait MyTrait', $output);
        $this->assertStringNotContainsString('namespace', $output);
    }

    public function testWithMethod(): void
    {
        $method = MethodGenerator::fromReflection(new ReflectionMethod(
            MethodGeneratorTestStub::class,
            'publicMethod'
        ));
        $gen = new TraitGenerator('MyTrait', null, [$method]);
        $output = $gen->generate();
        $this->assertStringContainsString('function publicMethod', $output);
    }

    public function testWithDocBlock(): void
    {
        $gen = new TraitGenerator('MyTrait', null, [], new DocBlockGenerator('Trait doc.'));
        $output = $gen->generate();
        $this->assertStringContainsString('Trait doc.', $output);
    }

    public function testAddTrait(): void
    {
        $gen = new TraitGenerator('MyTrait', 'Foo');
        $gen->addTrait('SomeTrait');
        $output = $gen->generate();
        $this->assertStringContainsString('use SomeTrait', $output);
    }

    public function testAddTraitAlias(): void
    {
        $gen = new TraitGenerator('MyTrait', 'Foo');
        $gen->addTrait('BaseTrait');
        $gen->addTraitAlias('BaseTrait::myMethod', 'aliasedMethod', Visibility::PUBLIC);
        $output = $gen->generate();
        $this->assertStringContainsString('aliasedMethod', $output);
    }

    public function testGetNode(): void
    {
        $gen = new TraitGenerator('MyTrait', null);
        $node = $gen->getNode();
        $this->assertSame('MyTrait', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = new TraitGenerator('MyTrait', null);
        $this->assertSame('MyTrait', $gen->name);
    }

    public function testImplementsGeneratorInterface(): void
    {
        $gen = new TraitGenerator('MyTrait', null);
        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the implemented interface)
        $this->assertInstanceOf(GeneratorInterface::class, $gen);
    }

    public function testAddTraitAliasPrivate(): void
    {
        $gen = new TraitGenerator('MyTrait', 'Foo');
        $gen->addTrait('BaseTrait');
        $gen->addTraitAlias('BaseTrait::myMethod', 'privateAlias', Visibility::PRIVATE);
        $output = $gen->generate();
        $this->assertStringContainsString('privateAlias', $output);
        $this->assertStringContainsString('private', $output);
    }

    public function testAddTraitAliasProtected(): void
    {
        $gen = new TraitGenerator('MyTrait', 'Foo');
        $gen->addTrait('BaseTrait');
        $gen->addTraitAlias('BaseTrait::myMethod', 'protectedAlias', Visibility::PROTECTED);
        $output = $gen->generate();
        $this->assertStringContainsString('protectedAlias', $output);
        $this->assertStringContainsString('protected', $output);
    }
}
