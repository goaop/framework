<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Dissect\Lexer\Exception\RecognitionException;
use Dissect\Parser\Exception\UnexpectedTokenException;
use Go\Aop\Advice;
use Go\Aop\Exception\PointcutSyntaxException;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Aop\PointcutAdvisor;
use Go\Core\AspectContainer;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;

/**
 * Lazy pointcut advisor is used to create a delayed pointcut only when needed
 */
final class LazyPointcutAdvisor implements PointcutAdvisor
{
    /**
     * Instance of parsed pointcut, parsed lazily on first access and memoized in the backing store
     */
    private Pointcut $pointcut {
        get => $this->pointcut ??= $this->parsePointcut();
    }

    /**
     * Creates the LazyPointcutAdvisor by specifying textual pointcut expression and Advice to run when Pointcut matches.
     *
     * @param string $pointcutExpression Pointcut expression represented with string
     */
    public function __construct(
        private readonly AspectContainer $container,
        private readonly string          $pointcutExpression,
        private readonly Advice          $advice,
    ) {}

    /**
     * Parses the pointcut expression, naming it in the error when it is invalid
     */
    private function parsePointcut(): Pointcut
    {
        try {
            return $this->container->getService(PointcutParser::class)->parse(
                $this->container->getService(PointcutLexer::class)->lex($this->pointcutExpression),
            );
        } catch (RecognitionException|UnexpectedTokenException $exception) {
            throw new PointcutSyntaxException(
                "Invalid pointcut expression `{$this->pointcutExpression}`: {$exception->getMessage()}",
                0,
                $exception,
            );
        }
    }

    #[\Override]
    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }

    #[\Override]
    public function getAdvice(): Advice
    {
        return $this->advice;
    }

    /**
     * Compiles into a generic advisor over the parsed pointcut: the compiled cache
     * resolves the parsing laziness away, so including it never needs the container
     */
    #[\Override]
    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(GenericPointcutAdvisor::class), [
            new Arg($this->pointcut->compileToPhp()),
            new Arg($this->advice->compileToPhp()),
        ]);
    }
}
