<?php

declare(strict_types = 1);
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
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;

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
        protected PointcutParser $pointcutParser
    ) {}

    /**
     * General method for parsing pointcuts
     *
     * @throws UnexpectedValueException if there was an error during parsing
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     */
    final protected function parsePointcut(
        Aspect $aspect,
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression
    ): Pointcut {
        $stream = $this->makeLexicalAnalyze($aspect, $reflection, $pointcutExpression);

        return $this->parseTokenStream($reflection, $pointcutExpression, $stream);
    }

    /**
     * Performs lexical analyze of pointcut
     *
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     *
     * @throws UnexpectedValueException
     */
    private function makeLexicalAnalyze(
        Aspect $aspect,
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression
    ): TokenStream {
        try {
            $resolvedThisPointcut = str_replace('$this', \get_class($aspect), $pointcutExpression);
            $stream = $this->pointcutLexer->lex($resolvedThisPointcut);
        } catch (RecognitionException $e) {
            $message = 'Can not recognize the lexical structure `%s` before %s, defined in %s:%d';
            $message = sprintf(
                $message,
                $pointcutExpression,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0
            );
            throw new UnexpectedValueException($message, 0, $e);
        }

        return $stream;
    }

    /**
     * Performs parsing of pointcut
     *
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass<T> $reflection
     * @template T of Aspect
     *
     * @throws UnexpectedValueException
     */
    private function parseTokenStream(
        ReflectionMethod|ReflectionProperty|ReflectionClass $reflection,
        string $pointcutExpression,
        TokenStream $stream
    ): Pointcut {
        try {
            $pointcut = $this->pointcutParser->parse($stream);
        } catch (UnexpectedTokenException $e) {
            $token   = $e->getToken();
            $message = 'Unexpected token %s in the `%s` before %s, defined in %s:%d.' . PHP_EOL;
            $message .= 'Expected one of: %s';
            $message = sprintf(
                $message,
                $token->getValue(),
                $pointcutExpression,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0,
                implode(', ', $e->getExpected())
            );
            throw new UnexpectedValueException($message, 0, $e);
        }

        return $pointcut;
    }
}
