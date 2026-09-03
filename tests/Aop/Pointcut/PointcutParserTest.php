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

use ArrayIterator;
use Dissect\Lexer\Lexer;
use Dissect\Lexer\TokenStream\TokenStream;
use Go\Stubs\StubPropertyModifiers;
use Go\Tests\TestProject\Application\ClassWithComplexTypes;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

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
        $this->parser = new PointcutParser(new PointcutGrammar());
    }

    /**
     * Tests parsing
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('validPointcutDefinitions')]
    public function testParsingExpression(string $pointcutExpression): void
    {
        $stream = $this->lexer->lex($pointcutExpression);
        $this->expectNotToPerformAssertions();
        $this->parser->parse($stream);
    }

    /**
     * @return array<array{string}>
     */
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

            // This will match static initialization pointcut
            ['staticinitialization(Some\Specific\Class\**)'],

            // This will match all methods, but not inherited
            ['execution(public **->*(*)) && !matchInherited()'],

            // This will match dynamic initialization pointcut
            ['initialization(Some\Specific\Class\**)'],

            // Union/intersection/DNF return types (issue #604)
            ['execution(public Example->method(*): string|int)'],
            ['execution(public Example->method(*): Countable&Iterator)'],
            ['execution(public Example->method(*): Iterator|Countable&Iterator|null)'],
            ['execution(Demo\Namespace\*(*): string|null)'],

            // readonly and asymmetric visibility modifiers (issue #604)
            ['access(readonly Example\Aspect\*->property*)'],
            ['access(private(set) Example\Aspect\*->property*)'],
            ['access(protected(set) Example\Aspect\*->property*)'],
            ['access(public|readonly **->*)'],
            ['access(final readonly Example->*)'],
        ];
    }

    /**
     * A parsed 'access(readonly ...)' pointcut must match only readonly properties.
     */
    public function testParsedReadonlyPointcutMatchesOnlyReadonlyProperties(): void
    {
        $pointcut = $this->parser->parse($this->lexer->lex('access(readonly **->*)'));

        $class = new \ReflectionClass(StubPropertyModifiers::class);
        $this->assertTrue($pointcut->matches($class, $class->getProperty('readonlyProp')));
        $this->assertFalse($pointcut->matches($class, $class->getProperty('plain')));
    }

    /**
     * Parsed 'private(set)' / 'protected(set)' pointcuts must match only properties
     * with the corresponding asymmetric set-visibility.
     */
    public function testParsedAsymmetricVisibilityPointcutMatchesOnlyMatchingProperties(): void
    {
        $privateSet = $this->parser->parse($this->lexer->lex('access(private(set) **->*)'));

        $class = new \ReflectionClass(StubPropertyModifiers::class);
        $this->assertTrue($privateSet->matches($class, $class->getProperty('privateSetProp')));
        $this->assertFalse($privateSet->matches($class, $class->getProperty('protectedSetProp')));
        $this->assertFalse($privateSet->matches($class, $class->getProperty('plain')));

        $protectedSet = $this->parser->parse($this->lexer->lex('access(protected(set) **->*)'));
        $this->assertTrue($protectedSet->matches($class, $class->getProperty('protectedSetProp')));
        $this->assertFalse($protectedSet->matches($class, $class->getProperty('privateSetProp')));
        $this->assertFalse($protectedSet->matches($class, $class->getProperty('plain')));
    }

    /**
     * A parsed execution pointcut with a union return type must match the method
     * with that union return type, member order being irrelevant.
     */
    public function testParsedUnionReturnTypePointcutMatches(): void
    {
        $expression = 'execution(public **->publicMethodWithUnionTypeReturn(*): Closure|Exception)';
        $pointcut   = $this->parser->parse($this->lexer->lex($expression));

        $class = new \ReflectionClass(ClassWithComplexTypes::class);
        $this->assertTrue($pointcut->matches($class, $class->getMethod('publicMethodWithUnionTypeReturn')));
        $this->assertFalse($pointcut->matches($class, $class->getMethod('publicMethodWithIntersectionTypeReturn')));
    }

    /**
     * A parsed execution pointcut with an intersection return type must match the method
     * with that intersection return type.
     */
    public function testParsedIntersectionReturnTypePointcutMatches(): void
    {
        $expression = 'execution(public **->*(*): Countable&Exception)';
        $pointcut   = $this->parser->parse($this->lexer->lex($expression));

        $class = new \ReflectionClass(ClassWithComplexTypes::class);
        $this->assertTrue($pointcut->matches($class, $class->getMethod('publicMethodWithIntersectionTypeReturn')));
        $this->assertFalse($pointcut->matches($class, $class->getMethod('publicMethodWithUnionTypeReturn')));
    }

    /**
     * If the underlying LALR parser ever finishes a token stream without reaching an
     * "accept" state (e.g. a stream with no tokens at all, or missing the trailing
     * $eof token), {@see \Dissect\Parser\LALR1\Parser::parse()} returns `null` instead
     * of throwing. PointcutParser::parse() guards against that and must reject a
     * non-Pointcut result.
     */
    public function testParseThrowsWhenUnderlyingParserDoesNotProduceAPointcut(): void
    {
        $emptyStream = new class implements TokenStream {
            public function getPosition(): int
            {
                return 0;
            }

            public function getCurrentToken(): \Dissect\Lexer\Token
            {
                throw new \OutOfBoundsException('No tokens in stream');
            }

            public function lookAhead(int $n): \Dissect\Lexer\Token
            {
                throw new \OutOfBoundsException('No tokens in stream');
            }

            public function get(int $n): \Dissect\Lexer\Token
            {
                throw new \OutOfBoundsException('No tokens in stream');
            }

            public function move(int $n): void
            {
            }

            public function seek(int $n): void
            {
            }

            public function next(): void
            {
                throw new \OutOfBoundsException('No tokens in stream');
            }

            public function count(): int
            {
                return 0;
            }

            /**
             * @return ArrayIterator<int, \Dissect\Lexer\Token>
             */
            public function getIterator(): ArrayIterator
            {
                return new ArrayIterator([]);
            }
        };

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Expected instance of Pointcut to be received during parsing');

        $this->parser->parse($emptyStream);
    }
}
