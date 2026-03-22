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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

class ValueGeneratorTest extends TestCase
{
    #[DataProvider('scalarProvider')]
    public function testScalarValues(mixed $input, string $expected): void
    {
        $gen = new ValueGenerator($input);
        $this->assertSame($expected, $gen->generate());
    }

    public static function scalarProvider(): array
    {
        return [
            'null'         => [null, 'null'],
            'true'         => [true, 'true'],
            'false'        => [false, 'false'],
            'int zero'     => [0, '0'],
            'int positive' => [42, '42'],
            'int negative' => [-7, '-7'],
            'float'        => [3.14, '3.14'],
            'float neg'    => [-1.5, '-1.5'],
            'empty string' => ['', "''"],
            'string'       => ['hello', "'hello'"],
            'string quotes'=> ["it's", "'it\\'s'"],
        ];
    }

    public function testEmptyArray(): void
    {
        $gen = new ValueGenerator([]);
        $this->assertSame('[]', $gen->generate());
    }

    public function testListArray(): void
    {
        $gen = new ValueGenerator(['a', 'b', 'c']);
        $output = $gen->generate();
        $this->assertStringContainsString("'a',", $output);
        $this->assertStringContainsString("'b',", $output);
        $this->assertStringContainsString("'c',", $output);
        // Multi-line for 3+ items
        $this->assertStringContainsString("\n", $output);
    }

    public function testAssocArray(): void
    {
        $gen = new ValueGenerator(['key' => 'value', 'x' => 42]);
        $output = $gen->generate();
        $this->assertStringContainsString("'key' => 'value',", $output);
        $this->assertStringContainsString("'x' => 42,", $output);
    }

    public function testNestedArray(): void
    {
        $gen = new ValueGenerator(['method' => ['foo' => ['advisor.Foo->bar']]]);
        $output = $gen->generate();
        $this->assertStringContainsString("'method'", $output);
        $this->assertStringContainsString("'foo'", $output);
        $this->assertStringContainsString("'advisor.Foo->bar',", $output);
        // Should be multi-line
        $this->assertStringContainsString("\n", $output);
    }

    public function testArrayDepthLimitsNesting(): void
    {
        $gen = new ValueGenerator(['method' => ['foo' => ['advisor.Foo->bar']]]);
        $gen->setArrayDepth(1);
        $output = $gen->generate();
        // At depth 1, nested value is truncated to []
        $this->assertStringContainsString('[]', $output);
    }

    public function testGetNodeReturnsExprNode(): void
    {
        $gen = new ValueGenerator(42);
        $node = $gen->getNode();
        $this->assertInstanceOf(Expr::class, $node);
        $this->assertInstanceOf(Scalar\Int_::class, $node);
    }

    public function testGetNodeNull(): void
    {
        $gen = new ValueGenerator(null);
        $node = $gen->getNode();
        $this->assertInstanceOf(Expr\ConstFetch::class, $node);
        $this->assertSame('null', (string) $node->name);
    }

    public function testGetNodeBool(): void
    {
        $gen = new ValueGenerator(true);
        $node = $gen->getNode();
        $this->assertInstanceOf(Expr\ConstFetch::class, $node);
        $this->assertSame('true', (string) $node->name);
    }

    public function testGetNodeArray(): void
    {
        $gen = new ValueGenerator(['a', 'b']);
        $node = $gen->getNode();
        $this->assertInstanceOf(Expr\Array_::class, $node);
        $this->assertCount(2, $node->items);
    }

    public function testThrowsForUnsupportedType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot generate AST node for value of type');
        $gen = new ValueGenerator(new \stdClass());
        $gen->getNode();
    }

    public function testMultiLineFormattingForNonEmptyArray(): void
    {
        $gen = new ValueGenerator(['a' => 1]);
        $output = $gen->generate();
        // Even a single-item array should be multi-line
        $this->assertStringContainsString("\n", $output);
        // Closing bracket on its own line
        $this->assertStringEndsWith(']', trim($output));
    }
}
