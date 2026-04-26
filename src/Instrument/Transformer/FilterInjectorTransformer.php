<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Instrument\ClassLoading\AopFileResolver;
use PhpParser\Node;
use PhpParser\Node\Expr\Include_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\FindingVisitor;

/**
 * Transformer that injects source filter for "require" and "include" operations
 *
 * Wraps include/require expressions with AopFileResolver::rewrite() calls so that
 * included files are also loaded through the AOP transformation pipeline.
 */
class FilterInjectorTransformer implements SourceTransformer
{
    /**
     * Wrap all includes into rewrite filter
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $includeExpressionFinder = new FindingVisitor(fn(Node $node) => $node instanceof Include_);

        // TODO: move this logic into walkSyntaxTree(Visitor $nodeVistor) method
        $traverser = new NodeTraverser();
        $traverser->addVisitor($includeExpressionFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var Include_[] $includeExpressions */
        $includeExpressions = $includeExpressionFinder->getFoundNodes();

        if (empty($includeExpressions)) {
            return TransformerResultEnum::RESULT_ABSTAIN;
        }

        foreach ($includeExpressions as $includeExpression) {
            $startPosition = $includeExpression->getAttribute('startTokenPos');
            $endPosition   = $includeExpression->getAttribute('endTokenPos');
            if (!is_int($startPosition) || !is_int($endPosition)) {
                continue;
            }

            $metadata->tokenStream[$startPosition]->text .= ' \\' . AopFileResolver::class . '::rewrite(';
            if ($metadata->tokenStream[$startPosition+1]->id === T_WHITESPACE) {
                unset($metadata->tokenStream[$startPosition+1]);
            }

            $metadata->tokenStream[$endPosition]->text .= ', __DIR__)';
        }

        return TransformerResultEnum::RESULT_TRANSFORMED;
    }
}
