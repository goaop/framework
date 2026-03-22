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
use PhpParser\Node\Stmt\ClassMethod;
use ReflectionClass;
use ReflectionMethod;

class MethodGeneratorTestStub
{
    public function publicMethod(string $name, int $count = 0): string
    {
        return str_repeat($name, $count);
    }

    protected function protectedMethod(): void {}

    private function privateMethod(): void {}

    public static function staticMethod(array $data): array
    {
        return $data;
    }

    final public function finalMethod(): void {}

    public function methodWithException(\Exception $ex): ?\Exception
    {
        return $ex;
    }
}

class MethodGeneratorTest extends TestCase
{
    private function getMethod(string $name): ReflectionMethod
    {
        return new ReflectionMethod(MethodGeneratorTestStub::class, $name);
    }

    public function testFromReflectionPublicMethod(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $output = $gen->generate();
        $this->assertStringContainsString('public function publicMethod', $output);
        $this->assertStringContainsString('string $name', $output);
        $this->assertStringContainsString('int $count = 0', $output);
        $this->assertStringContainsString(': string', $output);
    }

    public function testFromReflectionProtectedMethod(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('protectedMethod'));
        $output = $gen->generate();
        $this->assertStringContainsString('protected function protectedMethod', $output);
        $this->assertStringContainsString(': void', $output);
    }

    public function testFromReflectionPrivateMethod(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('privateMethod'));
        $output = $gen->generate();
        $this->assertStringContainsString('private function privateMethod', $output);
    }

    public function testFromReflectionStaticMethod(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('staticMethod'));
        $output = $gen->generate();
        $this->assertStringContainsString('public static function staticMethod', $output);
    }

    public function testFromReflectionFinalMethod(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('finalMethod'));
        $output = $gen->generate();
        $this->assertStringContainsString('final public function finalMethod', $output);
    }

    public function testFromReflectionWithClassTypeParam(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('methodWithException'));
        $output = $gen->generate();
        $this->assertStringContainsString('\Exception $ex', $output);
        $this->assertStringContainsString('?\Exception', $output);
    }

    public function testSetAndGetBody(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setBody("return 'test';");
        $body = $gen->getBody();
        $this->assertStringContainsString("return 'test'", $body);
    }

    public function testSetAndGetStmts(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setBody("return 42;");
        $stmts = $gen->getStmts();
        $this->assertNotNull($stmts);
        $this->assertNotEmpty($stmts);
    }

    public function testSetStmtsFromArray(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setBody("return 42;");
        $stmts = $gen->getStmts();

        $gen2 = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen2->setStmts($stmts);
        $this->assertSame($gen->getBody(), $gen2->getBody());
    }

    public function testGetNode(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $node = $gen->getNode();
        $this->assertInstanceOf(ClassMethod::class, $node);
        $this->assertSame('publicMethod', (string) $node->name);
    }

    public function testGetName(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $this->assertSame('publicMethod', $gen->getName());
    }

    public function testSetDocBlock(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setDocBlock(new DocBlockGenerator('Test docblock.'));
        $output = $gen->generate();
        $this->assertStringContainsString('Test docblock.', $output);
    }

    public function testSetVisibility(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setVisibility(MethodGenerator::VISIBILITY_PROTECTED);
        $output = $gen->generate();
        $this->assertStringContainsString('protected', $output);
    }

    public function testSetStatic(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setStatic(true);
        $output = $gen->generate();
        $this->assertStringContainsString('static', $output);
    }

    public function testSetReturnType(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setReturnType('int');
        $output = $gen->generate();
        $this->assertStringContainsString(': int', $output);
    }

    public function testGetBodyReturnsEmptyWhenNoStmts(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        // stmts not set yet — empty body
        $this->assertSame('', $gen->getBody());
    }

    public function testAddParameter(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $param = new ParameterGenerator('extra', TypeGenerator::fromTypeString('bool'));
        $gen->addParameter($param);
        $output = $gen->generate();
        $this->assertStringContainsString('bool $extra', $output);
    }

    public function testWideningMode(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'), true);
        $output = $gen->generate();
        // With widening, parameter types are dropped
        $this->assertStringNotContainsString('string $name', $output);
        $this->assertStringContainsString('$name', $output);
    }

    public function testSetAbstract(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setAbstract(true);
        $output = $gen->generate();
        $this->assertStringContainsString('abstract', $output);
        // Abstract method should have no body
        $this->assertNull($gen->getStmts());
    }

    public function testSetInterface(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setInterface(true);
        $output = $gen->generate();
        // Interface method should have no body (no braces)
        $this->assertNull($gen->getStmts());
    }

    public function testSetReturnsReference(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setReturnsReference(true);
        $output = $gen->generate();
        $this->assertStringContainsString('function &publicMethod', $output);
    }

    public function testSetReturnTypeFromTypeGenerator(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $typeGen = TypeGenerator::fromTypeString('?Exception');
        $gen->setReturnType($typeGen);
        $output = $gen->generate();
        $this->assertStringContainsString('?\Exception', $output);
    }

    public function testManualConstructor(): void
    {
        $gen = new MethodGenerator('myMethod');
        $gen->setVisibility(MethodGenerator::VISIBILITY_PUBLIC);
        $gen->setBody("return true;");
        $output = $gen->generate();
        $this->assertStringContainsString('function myMethod', $output);
        $this->assertStringContainsString('return true', $output);
    }

    public function testSetBodyEmptyString(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        $gen->setBody('');
        // Empty body results in empty stmts list
        $stmts = $gen->getStmts();
        $this->assertIsArray($stmts);
        $this->assertEmpty($stmts);
    }

    public function testVisibilityPrivate(): void
    {
        $gen = new MethodGenerator('myMethod');
        $gen->setVisibility(MethodGenerator::VISIBILITY_PRIVATE);
        $output = $gen->generate();
        $this->assertStringContainsString('private', $output);
    }

    public function testFromReflectionDocComment(): void
    {
        $gen = MethodGenerator::fromReflection($this->getMethod('publicMethod'));
        // publicMethod has no doc comment, so no docblock should be auto-added
        $output = $gen->generate();
        // Just check it generates without error
        $this->assertStringContainsString('function publicMethod', $output);
    }
}
