<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Dissect\Lexer\Exception\RecognitionException;
use Dissect\Lexer\TokenStream\TokenStream;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Lang\Annotation;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Abstract aspect loader
 */
abstract class AbstractAspectLoaderExtension implements AspectLoaderExtension
{

    /**
     * General method for parsing pointcuts
     *
     * @param AspectContainer $container Container
     * @param Aspect $aspect Instance of current aspect
     * @param Annotation\BaseAnnotation|Annotation\BaseInterceptor $metaInformation
     * @param mixed|\ReflectionMethod|\ReflectionProperty $reflection Reflection of point
     *
     * @throws \UnexpectedValueException if there was an error during parsing
     * @return Pointcut
     */
    protected function parsePointcut(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation)
    {
        $stream = $this->makeLexicalAnalyze($container, $aspect, $reflection, $metaInformation);
        return $this->parseTokenStream($container, $reflection, $metaInformation, $stream);
    }

    /**
     * Performs lexical analyze of pointcut
     *
     * @param AspectContainer $container Container instance
     * @param Aspect $aspect Instance of aspect
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param $metaInformation
     *
     * @return TokenStream
     * @throws \UnexpectedValueException
     */
    protected function makeLexicalAnalyze(AspectContainer $container, Aspect $aspect, $reflection, $metaInformation)
    {
        /** @var $lexer \Dissect\Lexer\Lexer */
        $lexer = $container->get('aspect.pointcut.lexer');
        try {
            $resolvedThisPointcut = str_replace('$this', get_class($aspect), $metaInformation->value);
            $stream = $lexer->lex($resolvedThisPointcut);
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
     * @param AspectContainer $container Instance of container
     * @param ReflectionMethod|ReflectionProperty $reflection
     * @param $metaInformation
     * @param TokenStream $stream
     * @return Pointcut
     *
     * @throws \UnexpectedValueException
     */
    protected function parseTokenStream(AspectContainer $container, $reflection, $metaInformation, $stream)
    {
        /** @var $parser \Dissect\Parser\Parser */
        $parser = $container->get('aspect.pointcut.parser');
        try {
            $pointcut = $parser->parse($stream);
        } catch (UnexpectedTokenException $e) {
            /** @var \Dissect\Lexer\Token $token */
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