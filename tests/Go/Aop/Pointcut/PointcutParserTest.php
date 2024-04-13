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

use Dissect\Lexer\Lexer;
use Go\Core\AspectContainer;
use PHPUnit\Framework\TestCase;

/**
 * Class PointcutParserTest defines common check for valid grammar parsing
 */
class PointcutParserTest extends TestCase
{
    protected Lexer $lexer;
    protected PointcutParser $parser;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->lexer  = new PointcutLexer();
        $container    = $this->createMock(AspectContainer::class);
        $this->parser = new PointcutParser(new PointcutGrammar($container));
    }

    /**
     * Tests parsing
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validPointcutDefinitions')]
    public function testParsingExpression(string $pointcutExpression): void
    {
        $stream = $this->lexer->lex($pointcutExpression);
        $result = $this->parser->parse($stream);
        $this->assertNotNull($result);
    }

    public static function validPointcutDefinitions(): array
    {
        return [
            // Execution pointcuts
            ['execution(public Example->method(*))'],
            ['execution(public Example->method|method1|method2(*))'],
            ['execution(final public Example\Aspect\*->method*(*))'],
            ['execution(protected|public **::*someStatic*Method*(*))'],

            // Return-type hints for PHP7
            ['execution(public Example->method(*): string)'],

            // This will match property that has First\Second\Annotation\Class annotation
            ['@access(First\Second\Annotation\Class)'],

            // This will match method execution that has First\Second\Annotation\Class annotation
            ['@execution(First\Second\Annotation\Class)'],

            // This will match all the methods in all classes of Go\Aspects\Blog\Package.
            ['within(Go\Aspects\Blog\Package\*)'],
            // This will match all the methods in all classes of Go\Aspects\Blog\Package and its sub packages.
            ['within(Go\Aspects\Blog\Package\**)'],
            // This will match all the methods in the DemoClass.
            ['within(Go\Aspects\Blog\Package\DemoClass)'],
            // This will match all the methods which are in classes which implement DemoInterface.
            ['within(DemoInterface+)'],
            // This will match all the methods in the class with specific annotation.
            ['@within(First\Second\Annotation\Class)'],

            // Access pointcuts
            ['access(public|protected Example\Aspect\*->property*)'],
            ['access(protected Test\Class*->someProtected*Property)'],

            // Logic pointcuts
            ['!within(DemoInterface\Test+)'],
            ['within(DemoInterface+) && within(Some\Namespace\**)'],
            ['within(DemoInterface+) || within(Some\Namespace\**)'],

            // Parenthesis
            ['within(DemoInterface+) && ( within(**) || within(*) )'],

            // Function pointcut
            ['execution(Demo\*\Test\**\*(*))'],
            ['execution(Demo\Namespace\array_*_er(*))'],
            ['execution(**\*(*))'],

            // Function with return-type
            ['execution(Demo\*\Test\**\*(*): bool)'],


            // Dynamic pointcut for methods via __callStatic and __call
            ['dynamic(public Demo\Example\DynamicMethodsDemo::find*(*))'],
            ['dynamic(public Demo\Example\DynamicMethodsDemo->save*(*))'],

            // This will match static initialization pointcut
            ['staticinitialization(Some\Specific\Class\**)'],

            // This will match all methods, but not inherited
            ['execution(public **->*(*)) && !matchInherited()'],

            // This will match dynamic initialization pointcut
            ['initialization(Some\Specific\Class\**)'],
        ];
    }

}
