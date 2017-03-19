<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Dissect\Parser\LALR1\Parser;

/**
 * Pointcut parser extends the default parser with parse table and strict typehint for grammar
 */
class PointcutParser extends Parser
{
    /**
     * {@inheritDoc}
     */
    public function __construct(PointcutGrammar $grammar)
    {
        $parseTable = include 'PointcutParseTable.php';
        parent::__construct($grammar, $parseTable);
    }
}
