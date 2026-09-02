<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\Advice;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Aop\PointcutAdvisor;
use Go\Core\AspectContainer;

/**
 * Lazy pointcut advisor is used to create a delayed pointcut only when needed
 */
final class LazyPointcutAdvisor implements PointcutAdvisor
{
    /**
     * Instance of parsed pointcut, parsed lazily on first access and memoized in the backing store
     */
    private Pointcut $pointcut {
        get => $this->pointcut ??= $this->container->getService(PointcutParser::class)->parse(
            $this->container->getService(PointcutLexer::class)->lex($this->pointcutExpression)
        );
    }

    /**
     * Creates the LazyPointcutAdvisor by specifying textual pointcut expression and Advice to run when Pointcut matches.
     *
     * @param string $pointcutExpression Pointcut expression represented with string
     */
    public function __construct(
        private readonly AspectContainer $container,
        private readonly string          $pointcutExpression,
        private readonly Advice          $advice
    ) {}

    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }

    public function getAdvice(): Advice
    {
        return $this->advice;
    }
}
