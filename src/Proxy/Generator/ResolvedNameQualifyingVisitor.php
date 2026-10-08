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
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Replaces every name that the NameResolver has resolved by its fully qualified form
 *
 * Copied attribute arguments keep their meaning in the namespace of the generated proxy this way: class
 * constant fetches, enum cases etc. stay unambiguous. Unresolved names (e.g. unqualified global constants
 * like PHP_INT_MAX with namespace fallback semantics) are kept as written.
 *
 * Stateless, so one instance serves every traversal.
 *
 * @internal
 */
final class ResolvedNameQualifyingVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Name && !($node instanceof Name\FullyQualified)) {
            $resolved = $node->getAttribute('resolvedName');
            if ($resolved instanceof Name) {
                return new Name\FullyQualified($resolved->toString(), $node->getAttributes());
            }
        }

        return null;
    }
}
