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
use Dissect\Lexer\Lexer;
use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Dissect\Parser\Parser;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Lang\Annotation;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Abstract aspect loader
 */
abstract class AbstractAspectLoaderExtension implements AspectLoaderExtension
{

    /**
     * Instance of pointcut lexer
     *
     * @var null|Lexer
     */
    protected $pointcutLexer = null;

    /**
     * Instance of pointcut parser
     *
     * @var null|Parser
     */
    protected $pointcutParser = null;

    /**
     * Default initialization of dependencies
     *
     * @param Lexer $pointcutLexer Instance of pointcut lexer
     * @param Parser $pointcutParser Instance of pointcut parser
     */
    public function __construct(Lexer $pointcutLexer, Parser $pointcutParser)
    {
        $this->pointcutLexer  = $pointcutLexer;
        $this->pointcutParser = $pointcutParser;
    }

    /**
     * General method for parsing pointcuts
     *
     * @param Aspect $aspect Instance of current aspect
     * @param Annotation\BaseAnnotation|Annotation\BaseInterceptor $metaInformation
     * @param mixed|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     *
     * @throws \UnexpectedValueException if there was an error during parsing
     * @return Pointcut|PointFilter
     */
    protected function parsePointcut(Aspect $aspect, $reflection, $metaInformation)
    {
        $stream = $this->makeLexicalAnalyze($aspect, $reflection, $metaInformation);

        return $this->parseTokenStream($reflection, $metaInformation, $stream);
    }

    /**
     * Performs lexical analyze of pointcut
     *
     * @param Aspect $aspect Instance of aspect
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param Annotation\BaseAnnotation $metaInformation
     *
     * @return TokenStream
     * @throws \UnexpectedValueException
     */
    protected function makeLexicalAnalyze(Aspect $aspect, $reflection, $metaInformation)
    {
        try {
            $resolvedThisPointcut = str_replace('$this', get_class($aspect), $metaInformation->value);
            $stream = $this->pointcutLexer->lex($resolvedThisPointcut);
        } catch (RecognitionException $e) {
            $message = "Can not recognize the lexical structure `%s` before %s, defined in %s:%d";
            $message = sprintf(
                $message,
                $metaInformation->value,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0
            );
            throw new \UnexpectedValueException($message, 0, $e);
        }

        return $stream;
    }

    /**
     * Performs parsing of pointcut
     *
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param Annotation\BaseAnnotation $metaInformation
     * @param TokenStream $stream
     * @return Pointcut
     *
     * @throws \UnexpectedValueException
     */
    protected function parseTokenStream($reflection, $metaInformation, $stream)
    {
        try {
            $pointcut = $this->pointcutParser->parse($stream);
        } catch (UnexpectedTokenException $e) {
            $token   = $e->getToken();
            $message = "Unexpected token %s in the `%s` before %s, defined in %s:%d." . PHP_EOL;
            $message .= "Expected one of: %s";
            $message = sprintf(
                $message,
                $token->getValue(),
                $metaInformation->value,
                (isset($reflection->class) ? $reflection->class . '->' : '') . $reflection->name,
                method_exists($reflection, 'getFileName')
                    ? $reflection->getFileName()
                    : $reflection->getDeclaringClass()->getFileName(),
                method_exists($reflection, 'getStartLine')
                    ? $reflection->getStartLine()
                    : 0,
                join(', ', $e->getExpected())
            );
            throw new \UnexpectedValueException($message, 0, $e);
        }

        return $pointcut;
    }
}
