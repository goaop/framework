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

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;

/**
 * Pretty-printer for generated proxy code.
 *
 * Keeps joinpoint initialization readable even when method bodies are parsed
 * through AST nodes before class generation.
 */
class GeneratedCodePrinter extends Standard
{
    protected function pExpr_Array(Expr\Array_ $node): string
    {
        if (empty($node->items)) {
            return $node->getAttribute('kind') === Expr\Array_::KIND_SHORT ? '[]' : 'array()';
        }
        if (!$this->isInterceptorArray($node)) {
            return parent::pExpr_Array($node);
        }

        $isShort = $node->getAttribute('kind') === Expr\Array_::KIND_SHORT;

        return ($isShort ? '[' : 'array(')
            . $this->pCommaSeparatedMultiline($node->items, true)
            . $this->nl
            . ($isShort ? ']' : ')');
    }

    protected function pExpr_StaticCall(Expr\StaticCall $node): string
    {
        if ($node->class instanceof Name && str_ends_with($node->class->toString(), 'InterceptorInjector')) {
            $name = $node->name instanceof Identifier ? $node->name->toString() : $this->p($node->name);

            return $this->pStaticDereferenceLhs($node->class) . '::' . $name
                . '(' . $this->pCommaSeparatedMultiline($node->args, true) . $this->nl . ')';
        }

        return parent::pExpr_StaticCall($node);
    }

    private function isInterceptorArray(Expr\Array_ $node): bool
    {
        foreach ($node->items as $item) {
            if (!$item->value instanceof Expr\StaticCall) {
                return false;
            }
            $call = $item->value;
            if (!$call->class instanceof Name || !str_ends_with($call->class->toString(), 'Interceptor')) {
                return false;
            }
        }

        return true;
    }
}
