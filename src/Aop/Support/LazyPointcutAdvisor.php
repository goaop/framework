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
use Go\Core\AspectContainer;

/**
 * Lazy pointcut advisor is used to create a delayed pointcut only when needed
 */
class LazyPointcutAdvisor extends AbstractGenericPointcutAdvisor
{

    /**
     * Pointcut expression
     *
     * @var string
     */
    private $pointcutExpression;

    /**
     * Instance of parsed pointcut
     *
     * @var Pointcut|null
     */
    private $pointcut;

    /**
     * @var AspectContainer
     */
    private $container;

    /**
     * Create a DefaultPointcutAdvisor, specifying Pointcut and Advice.
     *
     * @param AspectContainer $container Instance of container
     * @param string $pointcutExpression The Pointcut targeting the Advice
     * @param Advice $advice The Advice to run when Pointcut matches
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
    public function getPointcut() : Pointcut
    {
        if (!$this->pointcut) {

            // Inject this dependencies and make them lazy!

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
