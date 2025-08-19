<?php

namespace Go\Aop\Pointcut\DNF\Parser;

interface TokenizerParserInterface
{
    public function parse(string $input);
}