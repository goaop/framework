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
use PhpParser\Node\ComplexType;
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

    /**
     * @return array<string, array{string, string, array<string, string|null>}>
     */
    public static function importedTypeProvider(): array
    {
        return [
            'builtin'      => ['int', 'int', []],
            'keyword'      => ['?self', '?self', []],
            'class'        => ['App\Domain\Product', 'Product', ['App\Domain\Product' => null]],
            'nullable'     => ['?App\Domain\Product', '?Product', ['App\Domain\Product' => null]],
            'union'        => ['App\Money|Exception|null', 'Money|Exception|null', ['Exception' => null]],
            'intersection' => ['Countable&Iterator', 'Countable&Iterator', ['Countable' => null, 'Iterator' => null]],
            'dnf'          => [
                '(Countable&Iterator)|Other\Money|static',
                '(Countable&Iterator)|OtherMoney|static',
                ['Countable' => null, 'Iterator' => null, 'Other\Money' => 'OtherMoney'],
            ],
        ];
    }

    /**
     * @param array<string, string|null> $expectedUses
     */
    #[DataProvider('importedTypeProvider')]
    public function testWithImportedNamesReferencesClassesThroughImports(string $type, string $expected, array $expectedUses): void
    {
        $imports = new ProxyImports('App');
        $imports->import('App\Money');

        $this->assertSame($expected, TypeGenerator::fromTypeString($type)->withImportedNames($imports)->generate());
        $this->assertSame($expectedUses, $imports->getUses());
    }

    public function testWithoutImportsTheTypeStaysFullyQualified(): void
    {
        $type = TypeGenerator::fromTypeString('?App\Domain\Product');

        $this->assertSame($type, $type->withImportedNames(null));
        $this->assertSame('?\App\Domain\Product', $type->generate());
    }

    public function testRenderTypeForPhpDocUsesImports(): void
    {
        $imports = new ProxyImports('App', ['Lib\Product' => 'Product']);
        $node    = new \PhpParser\Node\UnionType([
            new \PhpParser\Node\Name('Product', ['resolvedName' => new \PhpParser\Node\Name\FullyQualified('Lib\Product')]),
            new \PhpParser\Node\Name\FullyQualified('Exception'),
            new \PhpParser\Node\Identifier('null'),
        ]);

        $this->assertSame('Product|Exception|null', TypeGenerator::renderAstTypeForPhpDoc($node, $imports));
        $this->assertSame('\Lib\Product|\Exception|null', TypeGenerator::renderAstTypeForPhpDoc($node));

        $intersection = new \PhpParser\Node\IntersectionType([
            new \PhpParser\Node\Name\FullyQualified('Countable'),
            new \PhpParser\Node\Name\FullyQualified('Lib\Product'),
        ]);
        $this->assertSame('Countable&Product', TypeGenerator::renderAstTypeForPhpDoc($intersection, $imports));
        $this->assertSame(
            '?Exception',
            TypeGenerator::renderAstTypeForPhpDoc(new \PhpParser\Node\NullableType(new \PhpParser\Node\Name\FullyQualified('Exception')), $imports),
        );
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function reflectionTypeForPhpDocProvider(): array
    {
        $ns = 'Go\Stubs\Generator';

        return [
            'class'        => [$ns . '\typeGenHelper_namedClass', 'Exception', '\Exception'],
            'nullable cls' => [$ns . '\typeGenHelper_nullableClass', '?Exception', '?\Exception'],
            'union'        => [$ns . '\typeGenHelper_union', 'string|int', 'string|int'],
            'intersection' => [$ns . '\typeGenHelper_intersection', 'Countable&Iterator', '\Countable&\Iterator'],
        ];
    }

    #[DataProvider('reflectionTypeForPhpDocProvider')]
    public function testRenderTypeForPhpDocOfReflectionTypeUsesImports(string $functionName, string $imported, string $qualified): void
    {
        $type = new ReflectionFunction($functionName)->getParameters()[0]->getType();

        $this->assertSame($imported, TypeGenerator::renderTypeForPhpDoc($type, new ProxyImports('App')));
        $this->assertSame($qualified, TypeGenerator::renderTypeForPhpDoc($type));
    }

    public function testWithImportedNamesKeepsKeywordNamesOfRawAstNodes(): void
    {
        $type = TypeGenerator::fromAstNode(new \PhpParser\Node\UnionType([
            new \PhpParser\Node\Name('static'),
            new \PhpParser\Node\Name\FullyQualified('App\Domain\Product'),
        ]));

        $this->assertSame('static|Product', $type->withImportedNames(new ProxyImports('App'))->generate());
    }

    public function testFromTypeStringThrowsOnMalformedDnf(): void
    {
        $this->expectException(WeavingException::class);
        $this->expectExceptionMessage('Malformed DNF type');
        TypeGenerator::fromTypeString('(Countable&Iterator|null');
    }

    public function testFromResolvedAstNodeRejectsUnknownComplexType(): void
    {
        $unknownType = new class extends ComplexType {
            public function getType(): string
            {
                return 'CustomType';
            }

            public function getSubNodeNames(): array
            {
                return [];
            }
        };

        try {
            TypeGenerator::fromResolvedAstNode($unknownType);
            $this->fail('An unknown complex type node must be rejected');
        } catch (WeavingException $exception) {
            $this->assertSame('Unsupported type node CustomType', $exception->getMessage());
        }
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
}
