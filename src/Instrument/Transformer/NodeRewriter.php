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

/**
 * Rule that rewrites the source tokens of single syntax tree nodes
 *
 * Rules do not walk the syntax tree themselves: {@see SyntaxTreeRewriter} walks it once per file
 * and hands every node to the rules declaring its type. A rule keeps no state between nodes, so
 * the same rule instance serves files that are transformed while another one is still in progress.
 */
interface NodeRewriter
{
    /**
     * Node classes (or their parents) this rule rewrites
     *
     * @return list<class-string<Node>>
     */
    public function getNodeTypes(): array;

    /**
     * Rewrites the source tokens of the given node in the token stream of the file
     *
     * @param list<Node> $ancestors Nodes enclosing the given one, from the outermost to its parent
     *
     * @return bool True if the token stream was changed
     */
    public function rewriteNode(Node $node, StreamMetaData $file, array $ancestors): bool;
}
