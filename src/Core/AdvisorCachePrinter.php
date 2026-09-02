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

namespace Go\Core;

use Go\Proxy\Generator\GeneratedCodePrinter;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Pretty-printer for compiled advisor cache files.
 *
 * Keeps the nested constructor graph readable: arrays are always printed one
 * item per line, and constructor calls with several arguments break their
 * argument list over multiple lines as well.
 *
 * @internal
 */
final class AdvisorCachePrinter extends GeneratedCodePrinter
{
    protected function pExpr_Array(Expr\Array_ $node): string
    {
        if (empty($node->items)) {
            return '[]';
        }

        return '[' . $this->pCommaSeparatedMultiline($node->items, true) . $this->nl . ']';
    }

    protected function pExpr_New(Expr\New_ $node): string
    {
        if (!$node->class instanceof Stmt\Class_ && count($node->args) > 1) {
            return 'new ' . $this->pNewOperand($node->class)
                . '(' . $this->pCommaSeparatedMultiline($node->args, true) . $this->nl . ')';
        }

        return parent::pExpr_New($node);
    }
}
