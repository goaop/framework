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
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\MagicConst;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PhpParser\NodeTraverser;
use PhpParser\Node\Identifier;
use PhpParser\NodeVisitor\FindingVisitor;

/**
 * Transformer that replaces magic __DIR__ and __FILE__ constants in the source code
 *
 * Additionally, ReflectionClass->getFileName() is also wrapped into AopFileResolver::resolveFileName()
 */
class MagicConstantTransformer implements SourceTransformer
{
    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResultEnum
    {
        $this->replaceMagicDirFileConstants($metadata);
        $this->wrapReflectionGetFileName($metadata);

        // We should always vote abstain, because if there is only changes for magic constants, we can drop them
        return TransformerResultEnum::RESULT_ABSTAIN;
    }

    /**
     * Wraps all possible getFileName() methods from ReflectionFile
     */
    private function wrapReflectionGetFileName(StreamMetaData $metadata): void
    {
        $methodCallFinder = new FindingVisitor(fn(Node $node) => $node instanceof MethodCall);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($methodCallFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MethodCall[] $methodCalls */
        $methodCalls = $methodCallFinder->getFoundNodes();
        foreach ($methodCalls as $methodCallNode) {
            if (($methodCallNode->name instanceof Identifier) && ($methodCallNode->name->toString() === 'getFileName')) {
                $startPosition    = $methodCallNode->getAttribute('startTokenPos');
                $endPosition      = $methodCallNode->getAttribute('endTokenPos');
                if (!is_int($startPosition) || !is_int($endPosition)) {
                    continue;
                }
                $expressionPrefix = '\\' . AopFileResolver::class . '::resolveFileName(';

                $metadata->tokenStream[$startPosition]->text = $expressionPrefix . $metadata->tokenStream[$startPosition]->text;
                $metadata->tokenStream[$endPosition]->text .= ')';
            }

        }
    }

    /**
     * Replaces all magic __DIR__ and __FILE__ constants in the file with calculated value
     */
    private function replaceMagicDirFileConstants(StreamMetaData $metadata): void
    {
        $magicConstFinder = new FindingVisitor(fn(Node $node) => $node instanceof Dir || $node instanceof File);
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($magicConstFinder);
        $traverser->traverse($metadata->syntaxTree);

        /** @var MagicConst[] $magicConstants */
        $magicConstants = $magicConstFinder->getFoundNodes();
        $magicFileValue = $metadata->uri;
        $magicDirValue  = dirname($magicFileValue);
        foreach ($magicConstants as $magicConstantNode) {
            $tokenPosition = $magicConstantNode->getAttribute('startTokenPos');
            if (!is_int($tokenPosition)) {
                continue;
            }
            $replacement = $magicConstantNode instanceof Dir ? $magicDirValue : $magicFileValue;

            $metadata->tokenStream[$tokenPosition]->text = "'{$replacement}'";
        }
    }
}
