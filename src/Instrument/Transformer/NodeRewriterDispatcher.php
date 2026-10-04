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

use Closure;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor of one syntax tree walk that passes every node to the rules declaring its type
 *
 * @internal Created by {@see SyntaxTreeRewriter} for every transformed file
 */
final class NodeRewriterDispatcher extends NodeVisitorAbstract
{
    private bool $isTransformed = false;

    /**
     * @param Closure(Node): list<NodeRewriter> $rulesFor Resolves the rules for a node, in the order of the rules
     * @param StreamMetaData                     $file     File whose token stream is rewritten
     */
    public function __construct(
        private readonly Closure $rulesFor,
        private readonly StreamMetaData $file,
    ) {}

    public function enterNode(Node $node): null
    {
        foreach (($this->rulesFor)($node) as $rule) {
            if ($rule->rewriteNode($node, $this->file)) {
                $this->isTransformed = true;
            }
        }

        return null;
    }

    /**
     * Checks if any rule changed the token stream of the file
     */
    public function isTransformed(): bool
    {
        return $this->isTransformed;
    }
}
