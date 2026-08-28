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

namespace Go\Instrument\Transformer;

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\StaticVar;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\NodeVisitorAbstract;

/**
 * Finds all `new` expressions that are legal to rewrite into runtime interceptor calls.
 *
 * Since PHP 8.1 `new` may appear inside constant-expression contexts: parameter default
 * values, static variable initializers, attribute arguments and global constants (and
 * php-parser also accepts it in property/class-constant defaults and enum case values).
 * Such occurrences must stay untouched — the interceptor rewrite
 * `...getInstance()->{Foo::class}(...)` is not a valid constant expression and would
 * trigger a compile-time fatal error (https://github.com/goaop/framework/issues/603).
 */
final class NewExpressionFinderVisitor extends NodeVisitorAbstract
{
    /**
     * @var list<New_>
     */
    private array $newExpressions = [];

    /**
     * Object ids of subtree roots that are constant-expression contexts
     *
     * @var array<int, true>
     */
    private array $constExprRoots = [];

    /**
     * How deep the traversal currently is inside constant-expression subtrees
     */
    private int $constExprDepth = 0;

    /**
     * @return list<New_> All found `new` expressions outside of constant-expression contexts
     */
    public function getFoundNewExpressions(): array
    {
        return $this->newExpressions;
    }

    #[\Override]
    public function enterNode(Node $node): null
    {
        if ($node instanceof Attribute) {
            // Attribute arguments are always constant expressions — skip the whole attribute.
            // Property hooks on promoted parameters contain runtime code, so for the other
            // containers only the initializer child expression is marked, not the container.
            $this->constExprRoots[spl_object_id($node)] = true;
        } else {
            $constExpr = match (true) {
                $node instanceof Param        => $node->default,
                $node instanceof StaticVar    => $node->default,
                $node instanceof PropertyItem => $node->default,
                $node instanceof Const_      => $node->value,
                $node instanceof EnumCase     => $node->expr,
                default                       => null,
            };
            if ($constExpr !== null) {
                $this->constExprRoots[spl_object_id($constExpr)] = true;
            }
        }

        if (isset($this->constExprRoots[spl_object_id($node)])) {
            ++$this->constExprDepth;
        }

        if ($this->constExprDepth === 0 && $node instanceof New_) {
            $this->newExpressions[] = $node;
        }

        return null;
    }

    #[\Override]
    public function leaveNode(Node $node): null
    {
        $nodeId = spl_object_id($node);
        if (isset($this->constExprRoots[$nodeId])) {
            --$this->constExprDepth;
            unset($this->constExprRoots[$nodeId]);
        }

        return null;
    }
}
