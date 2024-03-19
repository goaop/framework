<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PhpParser\Node\Name\FullyQualified;
use PhpParser\NodeTraverser;

/**
 * Transformer that replaces `self` constants in the source code, e.g. new self()
 */
class SelfValueTransformer extends BaseSourceTransformer
{
    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata): string
    {
        $selfValueVisitor = new SelfValueVisitor();
        $traverser        = new NodeTraverser();
        $traverser->addVisitor($selfValueVisitor);
        $traverser->traverse($metadata->syntaxTree);

        $this->adjustSelfTokens($metadata, $selfValueVisitor->getReplacedNodes());

        // We should always vote abstain, because if there are only changes for self we can drop them
        return self::RESULT_ABSTAIN;
    }

    /**
     * Adjusts tokens in the source code
     *
     * @param FullyQualified[] $replacedNodes Replaced nodes in the source code
     */
    private function adjustSelfTokens(StreamMetaData $metadata, array $replacedNodes): void
    {
        foreach ($replacedNodes as $replacedNode)
        {
            $position = $replacedNode->getAttribute('startTokenPos');
            $metadata->tokenStream[$position]->text = $replacedNode->toString();
        }
    }
}
