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
     * @var list<NodeRewriter>
     */
    private readonly array $rules;

    /**
     * Rules per node class for every set of rules applicable to a file, resolved on the first node of each class
     *
     * @var array<string, array<class-string<Node>, list<NodeRewriter>>>
     */
    private array $rulesByNodeClass = [];

    public function __construct(NodeRewriter ...$rules)
    {
        $this->rules = array_values($rules);
    }

    public function transform(StreamMetaData $metadata): TransformerResult
    {
        $rules = $this->getRulesForSource($metadata->originalSource);
        if ($rules === []) {
            return TransformerResult::Abstain;
        }

        $rulesKey = implode(',', array_keys($rules));
        $this->rulesByNodeClass[$rulesKey] ??= [];
        $dispatcher = new NodeRewriterDispatcher(array_values($rules), $this->rulesByNodeClass[$rulesKey], $metadata);
        $traverser  = new NodeTraverser($dispatcher);
        $traverser->traverse($metadata->syntaxTree);

        return $dispatcher->isTransformed() ? TransformerResult::Transformed : TransformerResult::Abstain;
    }

    /**
     * Returns the rules that can rewrite a node of the given source, keyed by their position
     *
     * @return array<int, NodeRewriter>
     */
    private function getRulesForSource(string $source): array
    {
        $rules = [];
        foreach ($this->rules as $index => $rule) {
            if (!$rule instanceof PrefilteredNodeRewriter
                || array_any($rule->getSourceMarkers(), static fn(string $marker): bool => stripos($source, $marker) !== false)
            ) {
                $rules[$index] = $rule;
            }
        }

        return $rules;
    }
}
