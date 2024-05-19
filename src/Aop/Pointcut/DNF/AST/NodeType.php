<?php

namespace Go\Aop\Pointcut\DNF\AST;

enum NodeType
{
    case IDENTIFIER;
    case AND;
    case OR;
}
