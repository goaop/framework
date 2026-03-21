<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2024, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use PhpParser\Node;
use PhpParser\Node\Scalar\MagicConst\Dir as MagicConstDir;
use PhpParser\Node\Scalar\MagicConst\File as MagicConstFile;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that replaces __DIR__ and __FILE__ magic constants inside source code with their values
 */
final class MagicDirFileConstantReplaceVisitor extends NodeVisitorAbstract
{
    public function __construct(private readonly StreamMetaData $metaData) {}

    /**
     * @inheritDoc
     *
     * @return String_|null Either replaces __DIR__/__FILE__ into string equivalent or returns null to skip
     */
    public function leaveNode(Node $node): String_|null
    {
        if ($node instanceof MagicConstFile) {
            return new String_($this->metaData->uri);
        }

        if ($node instanceof MagicConstDir) {
            return new String_(dirname($this->metaData->uri));
        }

        return null;
    }
}
