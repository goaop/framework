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

namespace Go\Instrument\Transformer;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Scalar\MagicConst\Dir as MagicConstDir;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that wraps include expressions with callback
 */
final class IncludeNodeWrapperVisitor extends NodeVisitorAbstract
{
    private readonly BuilderFactory $builder;

    /**
     * @param string $callbackMethodFQN Fully-qualified method name as a callback with leading '\' symbol
     */
    public function __construct(private readonly string $callbackMethodFQN)
    {
        $this->builder = new BuilderFactory();
    }

    /**
     * @inheritDoc
     *
     * @return null Always null, but we change AST of include expression before leaving the node
     */
    public function leaveNode(Node $node): null
    {
        if ($node instanceof Include_) {
            $node->expr = $this->wrapIncludeNodeExpression($node->expr);
        }

        return null;
    }

    /**
     * Wraps include expression with function callback: $callbackMethodFQN(string $originalInclude, __DIR__)
     */
    private function wrapIncludeNodeExpression(Expr $expressionNode): FuncCall
    {
        return $this->builder->funcCall(
            $this->callbackMethodFQN,
            $this->builder->args([
                $expressionNode,
                new MagicConstDir()
            ])
        );
    }
}
