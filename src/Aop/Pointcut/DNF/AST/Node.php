<?php

namespace Go\Aop\Pointcut\DNF\AST;

readonly class Node
{
    public function __construct(
        public NodeType $type,
        public ?string $identifier = null,
        public ?Node $left = null,
        public ?Node $right = null,
    ) {
    }
}
