<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Dissect\Lexer\SimpleLexer;

/**
 * This class defines a lexer for pointcut expression
 *
 * @package Go\Aop\Pointcut
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
        $this->token('within');
        $this->token('access');
        $this->token('@annotation');

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

        // Regex for class name
        $this->regex('NamePart', '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/');

        // NS separator
        $this->token('NsSeparator', '\\');

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
