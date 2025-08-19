<?php

namespace Go\Aop\Pointcut\DNF\Parser;

enum Token
{
    case EOF;
    case IDENTIFIER;
    case LPAREN;
    case RPAREN;
    case AND;
    case OR;
}
