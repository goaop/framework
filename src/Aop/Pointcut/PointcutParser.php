<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\LALR1\Parser;
use Go\Aop\Pointcut;

/**
 * Pointcut parser extends the default parser with parse table and strict typehint for grammar
 */
final class PointcutParser extends Parser
{
    public function __construct(PointcutGrammar $grammar)
    {
        $parseTable = include __DIR__ . '/PointcutParseTable.php';
        parent::__construct($grammar, $parseTable);
    }

    /**
     * @return Pointcut Covariant, always {@see Pointcut}
     */
    public function parse(TokenStream $stream): Pointcut
    {
        $result = parent::parse($stream);
        if (!$result instanceof Pointcut) {
            throw new \UnexpectedValueException("Expected instance of Pointcut to be received during parsing");
        }

        return $result;
    }
}
