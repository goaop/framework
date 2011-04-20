<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop;

/**
 * Pointcut realization for PHP
 *
 * Pointcuts are defined as a predicate over the syntax-tree of the program, and define an interface that constrains
 * which elements of the base program are exposed by the pointcut. A pointcut picks out certain join points and values
 * at those points
 *
 * @package go
 * @subpackage aop
 */
class Pointcut {

    protected $processor = null;

    public function __construct($processor)
    {
        if (!is_callable($processor)) {
            throw new \InvalidArgumentException('Processor should be callable');
        }
        $this->processor = $processor;
    }

    /**
     * @param string $className Name of class to check for
     * @param \org\aopalliance\intercept\Joinpoint[] $joinPoints
     * @param Aspect $aspect
     * @return void
     */
    function __invoke($className, array $joinPoints, Aspect $aspect)
    {
        $processor = $this->processor;
        $processor($className, $joinPoints, $aspect);
    }

}
