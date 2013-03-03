<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */


namespace Go\Aop\Pointcut;

/**
 * Class PointcutGrammarTest defines common check for valid grammar parsing
 */
use Dissect\Lexer\Lexer;
use Dissect\Parser\LALR1\Parser;

class PointcutGrammarTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var null|Lexer
     */
    protected $lexer = null;

    /**
     * @var null|Parser
     */
    protected $parser = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->lexer  = new PointcutLexer();
        $this->parser = new Parser(new PointcutGrammar());
    }

    /**
     * Tests parsing
     *
     * @dataProvider validPointcutDefinitions
     */
    public function testParsingExpression($pointcutExpression)
    {
        $stream = $this->lexer->lex($pointcutExpression);
        $result = $this->parser->parse($stream);
        $this->assertNotNull($result);
    }

    public function validPointcutDefinitions()
    {
        return array(
            // Execution pointcuts
            array('execution(public Example->method(*))'),
            array('execution(final public Example\Aspect\*->method*(*))'),
            array('execution(protected|public **::*someStatic*Method*(*))'),

            //array('@annotation(First\Second\Annotation\Class)'),

            // This will match all the methods in all classes of Go\Aspects\Blog\Package.
            array('within(Go\Aspects\Blog\Package\*)'),
            // This will match all the methods in all classes of Go\Aspects\Blog\Package and its sub packages.
            array('within(Go\Aspects\Blog\Package\**)'),
            // This will match all the methods in the DemoClass.
            array('within(Go\Aspects\Blog\Package\DemoClass)'),
            // This will match all the methods which are in classes which implement DemoInterface.
            array('within(DemoInterface+)'),

            // Access pointcuts
            array('access(* Example\Aspect\*->property*)'),
            array('access(protected Test\Class*->someProtected*Property)'),

            // Logic pointcuts
            array('!within(DemoInterface\Test+)'),
            array('within(DemoInterface+) && within(Some\Namespace\**)'),
            array('within(DemoInterface+) || within(Some\Namespace\**)'),

            // Parenthesis
            array('within(DemoInterface+) && ( within(**) || within(*) )'),
        );
    }

}