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

use Dissect\Lexer\SimpleLexer;

/**
 * This class defines a lexer for pointcut expression
 */
class PointcutLexer extends SimpleLexer
{

    /**
     * Lexer token definitions
     */
    public function __construct()
    {
        // General tokens
        $this->token('execution');
        $this->token('dynamic');
        $this->token('within');
        $this->token('access');
        $this->token('cflowbelow');
        $this->token('initialization');
        $this->token('staticinitialization');
        $this->token('matchInherited');

        // Parenthesis
        $this->token('(');
        $this->token(')');

        // Member modifiers
        $this->token('public');
        $this->token('protected');
        $this->token('private');
        $this->token('final');

        // Access type (dynamic or static)
        $this->token('->');
        $this->token('::');

        // Logic tokens
        $this->token('!');
        $this->token('&');
        $this->token('&&');
        $this->token('|');
        $this->token('||');

        $this->token('annotation', '@');

        // Regex for class name
        $this->regex('namePart', '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/');

        // NS separator
        $this->token('nsSeparator', '\\');

        // Special wildcard tokens
        $this->token('+');
        $this->token('*');
        $this->token('**');

        // White spaces
        $this->regex('WSP', "/^[ \r\n\t]+/");

        // Comments
        $this->regex('CMT', "|^//.*|");
        $this->skip('CMT', 'WSP');
    }
}
