<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeVisitorAbstract;

/**
 * Injects filename and stream metadata DTO into top-level namespace nodes.
 */
final class FileNameInjectorNodeVisitor extends NodeVisitorAbstract implements NodeTransformerResultReporter
{
    public function __construct(private readonly StreamMetaData $streamMetaData)
    {
    }

    public function beforeTraverse(array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if (!$node instanceof Namespace_) {
                continue;
            }

            $node->setAttribute(NodeTransformerAttribute::ORIGINAL_FILE_NAME, $this->streamMetaData->uri);
            $node->setAttribute(NodeTransformerAttribute::STREAM_METADATA, $this->streamMetaData);
        }

        return null;
    }

    public function getNodeTransformerResult(): TransformerResultEnum
    {
        return TransformerResultEnum::RESULT_ABSTAIN;
    }
}
