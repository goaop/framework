<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Dissect\Lexer\Exception\RecognitionException;
use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Go\Aop\Aspect;
use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Abstract aspect loader
 */
abstract class AbstractAspectLoaderExtension implements AspectLoaderExtension
{
    /**
     * Default loader constructor that accepts pointcut lexer and parser
     */
    public function __construct(
        protected PointcutLexer $pointcutLexer,
        protected PointcutParser $pointcutParser,
    ) {}

    /**
     * General method for parsing pointcuts
     *
     * @throws PointcutSyntaxException if there was an error during parsing
     * @param class-string<T>                                         $aspectClass Aspect the expression belongs to, `$this` in it
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     */
    final protected function parsePointcut(
        string $aspectClass,
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression,
    ): Pointcut {
        $stream = $this->makeLexicalAnalyze($aspectClass, $reflection, $pointcutExpression);

        return $this->parseTokenStream($reflection, $pointcutExpression, $stream);
    }

    /**
     * Performs lexical analyze of pointcut
     *
     * @param class-string<T>                                         $aspectClass
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     *
     * @throws PointcutSyntaxException
     */
    private function makeLexicalAnalyze(
        string $aspectClass,
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression,
    ): TokenStream {
        try {
            $resolvedThisPointcut = str_replace('$this', $aspectClass, $pointcutExpression);
            $stream = $this->pointcutLexer->lex($resolvedThisPointcut);
        } catch (RecognitionException $e) {
            throw PointcutSyntaxException::unrecognizedLexicalStructure($pointcutExpression, $reflection, $e);
        }

        return $stream;
    }

    /**
     * Performs parsing of pointcut
     *
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     *
     * @throws PointcutSyntaxException
     */
    private function parseTokenStream(
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression,
        TokenStream $stream,
    ): Pointcut {
        try {
            $pointcut = $this->pointcutParser->parse($stream);
        } catch (UnexpectedTokenException $e) {
            throw PointcutSyntaxException::unexpectedToken($pointcutExpression, $reflection, $e);
        }

        return $pointcut;
    }
}
