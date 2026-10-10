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

use Go\ParserReflection\ReflectionClass;
use Go\Proxy\ClassProxyGenerator;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Proxy of a class-like woven in the current file. The proxies of all class-likes of a file are written to one proxy
 * file once the whole file was woven.
 *
 * @internal
 */
final readonly class WovenProxy
{
    /**
     * Declaration of the woven class-like, converted to its `…OriginalTrait` in the woven source
     */
    public ClassLike $node;

    /**
     * @param int $endTokenPosition Position of the last token of the declaration
     */
    public function __construct(
        public ReflectionClass $class,
        public ClassProxyGenerator $generator,
        public int $endTokenPosition,
    ) {
        $this->node = $class->getNode();
    }
}
