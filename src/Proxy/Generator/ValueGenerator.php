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

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;

/**
 * Generates a PHP value expression (scalar, array, null, bool) as an AST node or PHP string.
 */
final class ValueGenerator
{
    private static ?Standard $printer = null;

    private mixed $value;
    private int $arrayDepth = 0;

    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Limits array representation to a specific nesting depth.
     * Depth 0 means unlimited, depth 1 means only top-level keys are preserved.
     */
    public function setArrayDepth(int $depth): void
    {
        $this->arrayDepth = $depth;
    }

    /**
     * Returns the underlying AST expression node.
     */
    public function getNode(): Expr
    {
        return $this->buildExprNode($this->value, 0);
    }

    /**
     * Generates the PHP source representation of the value.
     */
    public function generate(): string
    {
        return self::getPrinter()->prettyPrintExpr($this->getNode());
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function buildExprNode(mixed $value, int $currentDepth): Expr
    {
        if ($value === null) {
            return new Expr\ConstFetch(new \PhpParser\Node\Name('null'));
        }

        if (is_bool($value)) {
            return new Expr\ConstFetch(new \PhpParser\Node\Name($value ? 'true' : 'false'));
        }

        if (is_int($value)) {
            return new Int_($value);
        }

        if (is_float($value)) {
            return new Float_($value);
        }

        if (is_string($value)) {
            return new String_($value);
        }

        if (is_array($value)) {
            return $this->buildArrayNode($value, $currentDepth);
        }

        throw new \InvalidArgumentException('Cannot generate AST node for value of type: ' . get_debug_type($value));
    }

    /**
     * @param array<mixed> $value
     */
    private function buildArrayNode(array $value, int $currentDepth): Array_
    {
        $items = [];
        $isList = array_is_list($value);

        foreach ($value as $k => $v) {
            $keyNode = $isList ? null : $this->buildExprNode($k, $currentDepth + 1);

            if ($this->arrayDepth > 0 && $currentDepth >= $this->arrayDepth - 1 && is_array($v)) {
                // At depth limit, represent nested arrays as empty arrays
                $valueNode = new Array_([], ['kind' => Array_::KIND_SHORT]);
            } else {
                $valueNode = $this->buildExprNode($v, $currentDepth + 1);
            }

            $items[] = new ArrayItem($valueNode, $keyNode);
        }

        return new Array_($items, ['kind' => Array_::KIND_SHORT]);
    }

    private static function getPrinter(): Standard
    {
        if (self::$printer === null) {
            // Anonymous subclass: forces non-empty arrays to multi-line with trailing commas.
            // Empty arrays keep the compact `[]` form.
            self::$printer = new class (['shortArraySyntax' => true]) extends Standard {
                protected function pExpr_Array(Expr\Array_ $node): string
                {
                    if (empty($node->items)) {
                        return $node->getAttribute('kind') === Array_::KIND_SHORT ? '[]' : 'array()';
                    }
                    $isShort = $node->getAttribute('kind') === Array_::KIND_SHORT;
                    // pCommaSeparatedMultiline outdents internally; $this->nl after the call
                    // is already at the outer level — append it before the closing bracket.
                    return ($isShort ? '[' : 'array(')
                        . $this->pCommaSeparatedMultiline($node->items, true)
                        . $this->nl
                        . ($isShort ? ']' : ')');
                }
            };
        }
        return self::$printer;
    }
}
