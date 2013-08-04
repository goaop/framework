<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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