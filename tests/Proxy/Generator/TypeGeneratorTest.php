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

use Go\Aop\Exception\WeavingException;
use Go\ParserReflection\Resolver\TypeExpressionResolver;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

class TypeGeneratorTest extends TestCase
{
    private const STUBS_NS = 'Go\Stubs\Generator';

    #[DataProvider('fromTypeStringProvider')]
    public function testFromTypeString(string $input, string $expected): void
    {
        $gen = TypeGenerator::fromTypeString($input);
        $this->assertSame($expected, $gen->generate());
    }

    /**
     * @return array<string, array{string, string}>
     */
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
            'union with null' => ['int|null', 'int|null'],
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

    /**
     * @return array<string, array{string, string}>
     */
    public static function fromReflectionTypeProvider(): array
    {
        $ns = 'Go\Stubs\Generator';
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
        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Malformed DNF type');
        TypeGenerator::fromTypeString('(Countable&Iterator|null');
    }

    /**
     * The AST path builds the same type as the former round trip through TypeExpressionResolver(null, null)
     * and fromReflectionType(): generated proxies stay byte-identical
     */
    #[DataProvider('declaredTypeProvider')]
    public function testFromResolvedAstNodeMatchesResolvedReflectionType(string $declaredType, string $expected): void
    {
        $code = <<<PHP
            <?php
            namespace App\\Model;

            use Vendor\\Lib\\Collection;
            use Vendor\\Lib as Lib;

            function typed(): {$declaredType} {}
            PHP;
        $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse($code);
        $this->assertNotNull($statements);
        $statements = (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($statements);
        $function   = (new NodeFinder())->findFirstInstanceOf($statements, Function_::class);
        $this->assertInstanceOf(Function_::class, $function);
        $typeNode = $function->returnType;
        $this->assertNotNull($typeNode);

        $resolver = new TypeExpressionResolver(null, null);
        $resolver->process($typeNode, false);
        $resolvedType = $resolver->getType();
        $this->assertNotNull($resolvedType);

        $this->assertSame($expected, TypeGenerator::fromReflectionType($resolvedType)->generate());
        $this->assertSame($expected, TypeGenerator::fromResolvedAstNode($typeNode)->generate());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function declaredTypeProvider(): array
    {
        return [
            'builtin'             => ['int', 'int'],
            'builtin upper case'  => ['INT', 'int'],
            'mixed'               => ['mixed', 'mixed'],
            'static'              => ['static', 'static'],
            'self'                => ['self', 'self'],
            'parent'              => ['parent', 'parent'],
            'self upper case'     => ['SELF', 'self'],
            'imported class'      => ['Collection', '\Vendor\Lib\Collection'],
            'aliased namespace'   => ['Lib\Item', '\Vendor\Lib\Item'],
            'relative class'      => ['Entity', '\App\Model\Entity'],
            'fully qualified'     => ['\Countable', '\Countable'],
            'nullable builtin'    => ['?string', '?string'],
            'nullable class'      => ['?Collection', '?\Vendor\Lib\Collection'],
            'nullable self'       => ['?self', '?self'],
            'union with null'     => ['Collection|null', '\Vendor\Lib\Collection|null'],
            'union'               => ['int|string|false', 'int|string|false'],
            'intersection'        => ['\Countable&Collection', '\Countable&\Vendor\Lib\Collection'],
            'dnf'                 => ['(\Countable&Collection)|Entity|null', '(\Countable&\Vendor\Lib\Collection)|\App\Model\Entity|null'],
        ];
    }

    public function testFromReflectionIntersectionType(): void
    {
        $paramType = (new ReflectionFunction(self::STUBS_NS . '\typeGenHelper_intersection'))->getParameters()[0]->getType();
        $this->assertNotNull($paramType);
        $gen = TypeGenerator::fromReflectionType($paramType);
        $output = $gen->generate();
        $this->assertStringContainsString('Countable', $output);
        $this->assertStringContainsString('Iterator', $output);
        $this->assertStringContainsString('&', $output);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideScopeKeywordTypes(): iterable
    {
        yield 'self' => ['self', '\\App\\Child'];
        yield 'nullable self' => ['?self', '?\\App\\Child'];
        yield 'parent' => ['parent', '\\App\\Base'];
        yield 'union' => ['self|int|null', '\\App\\Child|int|null'];
        yield 'intersection' => ['self&\\Countable', '\\App\\Child&\\Countable'];
        yield 'dnf' => ['(self&\\Countable)|parent', '(\\App\\Child&\\Countable)|\\App\\Base'];
        yield 'static stays' => ['static', 'static'];
        yield 'builtin stays' => ['?int', '?int'];
        yield 'class stays' => ['\\App\\Other', '\\App\\Other'];
    }

    #[DataProvider('provideScopeKeywordTypes')]
    public function testResolveScopeKeywordsSpellsOutSelfAndParent(string $declared, string $expected): void
    {
        $resolved = TypeGenerator::fromTypeString($declared)->resolveScopeKeywords('App\\Child', 'App\\Base');

        $this->assertSame($expected, $resolved->generate());
        $this->assertSame($declared, TypeGenerator::fromTypeString($declared)->generate(), 'The original generator is untouched');
    }

    public function testResolveScopeKeywordsKeepsParentWithoutAParentClass(): void
    {
        $this->assertSame('?parent', TypeGenerator::fromTypeString('?parent')->resolveScopeKeywords('App\\Child', null)->generate());
    }
}
