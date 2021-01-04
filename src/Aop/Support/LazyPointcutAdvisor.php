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
use Go\Aop\PointcutAdvisor;
use Go\Core\AspectContainer;

/**
 * Lazy pointcut advisor is used to create a delayed pointcut only when needed
 */
class LazyPointcutAdvisor extends AbstractGenericAdvisor implements PointcutAdvisor
{
    /**
     * Pointcut expression represented with string
     */
    private string $pointcutExpression;

    /**
     * Instance of parsed pointcut
     */
    private ?Pointcut $pointcut = null;

    /**
     * Instance of aspect container
     */
    private AspectContainer $container;

    /**
     * Creates the LazyPointcutAdvisor by specifying textual pointcut expression and Advice to run when Pointcut matches.
     */
    public function __construct(AspectContainer $container, string $pointcutExpression, Advice $advice)
    {
        $this->container          = $container;
        $this->pointcutExpression = $pointcutExpression;
        parent::__construct($advice);
    }

    /**
     * Get the Pointcut that drives this advisor.
     */
    public function getPointcut(): Pointcut
    {
        if ($this->pointcut === null) {
            // Inject these dependencies and make them lazy!

            /** @var Pointcut\PointcutLexer $lexer */
            $lexer = $this->container->get('aspect.pointcut.lexer');

            /** @var Pointcut\PointcutParser $parser */
            $parser = $this->container->get('aspect.pointcut.parser');

            $tokenStream    = $lexer->lex($this->pointcutExpression);
            $this->pointcut = $parser->parse($tokenStream);
        }

        return $this->pointcut;
    }
}
