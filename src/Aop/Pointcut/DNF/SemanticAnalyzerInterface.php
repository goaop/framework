<?php

namespace Go\Aop\Pointcut\DNF;

use Go\Aop\Pointcut\DNF\AST\Node;
use Go\ParserReflection\ReflectionFileNamespace;

interface SemanticAnalyzerInterface
{
    public function verifyTree(Node $tree, \ReflectionClass|ReflectionFileNamespace $val);
}