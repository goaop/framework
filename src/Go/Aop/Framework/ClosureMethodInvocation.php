<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

/**
 * @package go
 */
class ClosureMethodInvocation extends AbstractMethodInvocation
{
    /** @var null|\Closure */
    protected $closureToCall = null;

    public function __construct($closureToCall, $classNameOrObject, $methodName, array $advices)
    {
        $this->closureToCall = $closureToCall;
        parent::__construct($classNameOrObject, $methodName, $advices);
    }

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    protected function invokeOriginalMethod()
    {
        $closureToCall = $this->closureToCall;
        return $closureToCall($this->methodName, $this->arguments);
    }
}
