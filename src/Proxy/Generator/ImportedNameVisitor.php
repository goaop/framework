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

use PhpParser\Node;
use PhpParser\Node\Attribute;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;

/**
 * Rewrites the names of cloned nodes for a generated file: class references through its imports
 *
 * Runs after a CloningVisitor, so every node it sees is already a copy. A name resolved by the NameResolver
 * is fully qualified first (functions and constants stay that way); the node holding a class reference then
 * replaces the fully qualified class name by the alias {@see ProxyImports::import()} returns. Unresolved names
 * (unqualified global constants like PHP_INT_MAX with namespace fallback semantics) and the `self`, `parent`
 * and `static` keywords are kept as written.
 *
 * @internal
 */
final class ImportedNameVisitor extends NodeVisitorAbstract
{
    public function __construct(
        private readonly ProxyImports $imports,
    ) {}

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Name) {
            if ($node instanceof FullyQualified || $node->isSpecialClassName()) {
                return null;
            }
            $resolved = $node->getAttribute('resolvedName');

            return $resolved instanceof Name ? new FullyQualified($resolved->toString(), $node->getAttributes()) : null;
        }

        if ($node instanceof ClassConstFetch
            || $node instanceof StaticCall
            || $node instanceof StaticPropertyFetch
            || $node instanceof New_
            || $node instanceof Instanceof_
        ) {
            $node->class = $this->importClassName($node->class);
        } elseif ($node instanceof Attribute) {
            $node->name = $this->importClassName($node->name);
        } elseif ($node instanceof Param || $node instanceof NullableType) {
            $node->type = $this->importClassName($node->type);
        } elseif ($node instanceof Closure || $node instanceof ArrowFunction) {
            $node->returnType = $this->importClassName($node->returnType);
        } elseif ($node instanceof UnionType || $node instanceof IntersectionType || $node instanceof Catch_) {
            $node->types = array_map($this->importClassName(...), $node->types);
        }

        return null;
    }

    /**
     * Returns the imported name for a fully qualified class name, any other node as it is
     *
     * @template T of Node|null
     *
     * @param T $node
     *
     * @return ($node is FullyQualified ? Name : T)
     */
    private function importClassName(?Node $node): ?Node
    {
        if (!$node instanceof FullyQualified) {
            return $node;
        }

        return new Name($this->imports->import($node->toString()), $node->getAttributes());
    }
}
