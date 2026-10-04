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
use PhpParser\NodeTraverser;

/**
 * Source transformer that walks the syntax tree of a file once and applies node rewriting rules
 *
 * Every node is passed to the rules declaring its type, in the order of the rules.
 */
final class SyntaxTreeRewriter implements SourceTransformer
{
    /**
     * @var array<NodeRewriter>
     */
    private readonly array $rules;

    /**
     * Rules per node class, resolved on the first node of each class
     *
     * @var array<class-string<Node>, list<NodeRewriter>>
     */
    private array $rulesByNodeClass = [];

    public function __construct(NodeRewriter ...$rules)
    {
        $this->rules = $rules;
    }

    public function transform(StreamMetaData $metadata): TransformerResult
    {
        if ($this->rules === []) {
            return TransformerResult::Abstain;
        }

        $dispatcher = new NodeRewriterDispatcher($this->getRulesFor(...), $metadata);
        $traverser  = new NodeTraverser($dispatcher);
        $traverser->traverse($metadata->syntaxTree);

        return $dispatcher->isTransformed() ? TransformerResult::Transformed : TransformerResult::Abstain;
    }

    /**
     * Returns the rules declaring the type of the given node, in the order of the rules
     *
     * @return list<NodeRewriter>
     */
    private function getRulesFor(Node $node): array
    {
        $nodeClass = $node::class;
        if (!isset($this->rulesByNodeClass[$nodeClass])) {
            $matchingRules = [];
            foreach ($this->rules as $rule) {
                foreach ($rule->getNodeTypes() as $nodeType) {
                    if ($node instanceof $nodeType) {
                        $matchingRules[] = $rule;
                        break;
                    }
                }
            }
            $this->rulesByNodeClass[$nodeClass] = $matchingRules;
        }

        return $this->rulesByNodeClass[$nodeClass];
    }
}
