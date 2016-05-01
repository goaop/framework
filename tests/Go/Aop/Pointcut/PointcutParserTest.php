<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Go\Aop\Pointcut;

/**
 * Class PointcutParserTest defines common check for valid grammar parsing
 */
use Dissect\Lexer\Lexer;
use Doctrine\Common\Annotations\Reader;
use Go\Core\AspectContainer;

class PointcutParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var null|Lexer
     */
    protected $lexer = null;

    /**
     * @var null|PointcutParser
     */
    protected $parser = null;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();
        $this->lexer  = new PointcutLexer();
        $container    = $this->getMock(AspectContainer::class);
        $annotReader  = $this->getMock(Reader::class);
        $this->parser = new PointcutParser(new PointcutGrammar($container, $annotReader));
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
            array('execution(public Example->method|method1|method2(*))'),
            array('execution(final public Example\Aspect\*->method*(*))'),
            array('execution(protected|public **::*someStatic*Method*(*))'),

            // This will match property that has First\Second\Annotation\Class annotation
            array('@access(First\Second\Annotation\Class)'),

            // This will match method execution that has First\Second\Annotation\Class annotation
            array('@execution(First\Second\Annotation\Class)'),

            // This will match all the methods in all classes of Go\Aspects\Blog\Package.
            array('within(Go\Aspects\Blog\Package\*)'),
            // This will match all the methods in all classes of Go\Aspects\Blog\Package and its sub packages.
            array('within(Go\Aspects\Blog\Package\**)'),
            // This will match all the methods in the DemoClass.
            array('within(Go\Aspects\Blog\Package\DemoClass)'),
            // This will match all the methods which are in classes which implement DemoInterface.
            array('within(DemoInterface+)'),
            // This will match all the methods in the class with specific annotation.
            array('@within(First\Second\Annotation\Class)'),

            // Access pointcuts
            array('access(public|protected Example\Aspect\*->property*)'),
            array('access(protected Test\Class*->someProtected*Property)'),

            // Logic pointcuts
            array('!within(DemoInterface\Test+)'),
            array('within(DemoInterface+) && within(Some\Namespace\**)'),
            array('within(DemoInterface+) || within(Some\Namespace\**)'),

            // Parenthesis
            array('within(DemoInterface+) && ( within(**) || within(*) )'),

            // Control flow execution pointcuts
            array('cflowbelow(execution(public Example->method(*)))'),

            // Function pointcut
            array('execution(Demo\*\Test\**\*(*))'),
            array('execution(Demo\Namespace\array_*_er(*))'),
            array('execution(**\*(*))'),

            // Dynamic pointcut for methods via __callStatic and __call
            array('dynamic(public Demo\Example\DynamicMethodsDemo::find*(*))'),
            array('dynamic(public Demo\Example\DynamicMethodsDemo->save*(*))'),

            // This will match static initialization pointcut
            array('staticinitialization(Some\Specific\Class\**)'),

            // This will match all methods, but not inherited
            array('execution(public **->*(*)) && !matchInherited()'),

            // This will match dynamic initialization pointcut
            array('initialization(Some\Specific\Class\**)'),
        );
    }

}
