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
     * Nodes enclosing the current one, from the outermost to its parent
     *
     * @var list<Node>
     */
    private array $ancestors = [];

    /**
     * Rules per node class, shared by the files with the same rules
     *
     * @var array<class-string<Node>, list<NodeRewriter>>
     */
    private array $rulesByNodeClass;

    /**
     * @param list<NodeRewriter>                            $rules            Rules applicable to the file, in their order
     * @param array<class-string<Node>, list<NodeRewriter>> $rulesByNodeClass Rules per node class, filled on the first
     *                                                                        node of each class
     * @param StreamMetaData                                $file             File whose token stream is rewritten
     */
    public function __construct(
        private readonly array $rules,
        array &$rulesByNodeClass,
        private readonly StreamMetaData $file,
    ) {
        $this->rulesByNodeClass = &$rulesByNodeClass;
    }

    public function enterNode(Node $node): null
    {
        foreach ($this->rulesByNodeClass[$node::class] ?? $this->resolveRules($node) as $rule) {
            if ($rule->rewriteNode($node, $this->file, $this->ancestors)) {
                $this->isTransformed = true;
            }
        }
        $this->ancestors[] = $node;

        return null;
    }

    public function leaveNode(Node $node): null
    {
        array_pop($this->ancestors);

        return null;
    }

    /**
     * Returns the rules declaring the type of the given node, in the order of the rules
     *
     * @return list<NodeRewriter>
     */
    private function resolveRules(Node $node): array
    {
        $matchingRules = [];
        foreach ($this->rules as $rule) {
            if (array_any($rule->getNodeTypes(), static fn(string $nodeType): bool => $node instanceof $nodeType)) {
                $matchingRules[] = $rule;
            }
        }

        return $this->rulesByNodeClass[$node::class] = $matchingRules;
    }

    /**
     * Checks if any rule changed the token stream of the file
     */
    public function isTransformed(): bool
    {
        return $this->isTransformed;
    }
}
