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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

class TypeGeneratorTest extends TestCase
{
    private const STUBS_NS = 'Go\Proxy\Generator\Stubs';

    #[DataProvider('fromTypeStringProvider')]
    public function testFromTypeString(string $input, string $expected): void
    {
        $gen = TypeGenerator::fromTypeString($input);
        $this->assertSame($expected, $gen->generate());
    }

    public static function fromTypeStringProvider(): array
    {
        return [
            'int'            => ['int', 'int'],
            'string'         => ['string', 'string'],
            'float'          => ['float', 'float'],
            'bool'           => ['bool', 'bool'],
            'array'          => ['array', 'array'],
            'callable'       => ['callable', 'callable'],
            'void'           => ['void', 'void'],
            'null'           => ['null', 'null'],
            'never'          => ['never', 'never'],
            'mixed'          => ['mixed', 'mixed'],
            'false'          => ['false', 'false'],
            'true'           => ['true', 'true'],
            'self'           => ['self', 'self'],
            'static'         => ['static', 'static'],
            'parent'         => ['parent', 'parent'],
            'nullable int'   => ['?int', '?int'],
            'nullable class' => ['?Exception', '?\Exception'],
            'FQN class'      => ['Exception', '\Exception'],
            'namespaced'     => ['Foo\Bar\Baz', '\Foo\Bar\Baz'],
            'FQN with slash' => ['\Exception', '\Exception'],
            'union'          => ['int|string', 'int|string'],
            'union with null'=> ['int|null', 'int|null'],
            'intersection'   => ['Countable&Iterator', '\Countable&\Iterator'],
            'dnf'            => ['(Countable&Iterator)|null', '(\Countable&\Iterator)|null'],
        ];
    }

    #[DataProvider('fromReflectionTypeProvider')]
    public function testFromReflectionType(string $functionName, string $expected): void
    {
        $param = (new ReflectionFunction($functionName))->getParameters()[0] ?? null;
        if ($param === null) {
            // void return type
            $type = (new ReflectionFunction($functionName))->getReturnType();
        } else {
            $type = $param->getType();
        }
        $this->assertNotNull($type);
        $gen = TypeGenerator::fromReflectionType($type);
        $this->assertSame($expected, $gen->generate());
    }

    public static function fromReflectionTypeProvider(): array
    {
        $ns = 'Go\Proxy\Generator\Stubs';
        return [
            'int'          => [$ns . '\typeGenHelper_namedInt', 'int'],
            'string'       => [$ns . '\typeGenHelper_namedString', 'string'],
            'float'        => [$ns . '\typeGenHelper_namedFloat', 'float'],
            'bool'         => [$ns . '\typeGenHelper_namedBool', 'bool'],
            'array'        => [$ns . '\typeGenHelper_namedArray', 'array'],
            'mixed'        => [$ns . '\typeGenHelper_namedMixed', 'mixed'],
            'object'       => [$ns . '\typeGenHelper_namedObject', 'object'],
            'class'        => [$ns . '\typeGenHelper_namedClass', '\Exception'],
            'nullable'     => [$ns . '\typeGenHelper_nullable', '?string'],
            'nullable cls' => [$ns . '\typeGenHelper_nullableClass', '?\Exception'],
            'union'        => [$ns . '\typeGenHelper_union', 'string|int'],
            'union+null'   => [$ns . '\typeGenHelper_unionWithNull', '?int'],
        ];
    }

    public function testGetNodeReturnsAstNode(): void
    {
        $gen = TypeGenerator::fromTypeString('int');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\Identifier::class, $node);
    }

    public function testGetNodeForClassReturnsFullyQualified(): void
    {
        $gen = TypeGenerator::fromTypeString('Exception');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\Name\FullyQualified::class, $node);
    }

    public function testGetNodeForNullableReturnsNullableType(): void
    {
        $gen = TypeGenerator::fromTypeString('?string');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\NullableType::class, $node);
    }

    public function testGetNodeForUnionReturnsUnionType(): void
    {
        $gen = TypeGenerator::fromTypeString('int|string');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\UnionType::class, $node);
    }

    public function testGetNodeForIntersectionReturnsIntersectionType(): void
    {
        $gen = TypeGenerator::fromTypeString('Countable&Iterator');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\IntersectionType::class, $node);
    }

    public function testGetNodeForDnfReturnsUnionType(): void
    {
        $gen = TypeGenerator::fromTypeString('(Countable&Iterator)|null');
        $node = $gen->getNode();
        $this->assertInstanceOf(\PhpParser\Node\UnionType::class, $node);
        // First element should be IntersectionType
        $this->assertInstanceOf(\PhpParser\Node\IntersectionType::class, $node->types[0]);
    }

    public function testFromTypeStringThrowsOnMalformedDnf(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Malformed DNF type');
        TypeGenerator::fromTypeString('(Countable&Iterator|null');
    }

    public function testFromReflectionIntersectionType(): void
    {
        $param = (new ReflectionFunction(self::STUBS_NS . '\typeGenHelper_intersection'))->getParameters()[0];
        $gen = TypeGenerator::fromReflectionType($param->getType());
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
        $this->assertStringContainsString('Iterator', $output);
        $this->assertStringContainsString('&', $output);
    }
}
