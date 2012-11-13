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
    /**
     * Closure to use
     *
     * @var null|\Closure
     */
    private $closureToCall = null;

    /**
     * Name of the parent class to use
     *
     * @var string
     */
    private $parentClass = '';

    public function __construct($closureToCall, $classNameOrObject, $methodName, array $advices)
    {
        $this->parentClass   = get_parent_class($classNameOrObject);
        $this->closureToCall = $closureToCall;
        parent::__construct($classNameOrObject, $methodName, $advices);
    }

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        // Delegate default logic to the parent
        if (isset($this->advices[$this->current])) {
            return parent::proceed();
        }

        if (is_string($this->instance)) {
            $closureToCall = $this->closureToCall->bindTo(null, $this->instance);
        } else {
            $closureToCall = $this->closureToCall->bindTo($this->instance, $this->classOrObject);
        }
        return $closureToCall($this->parentClass, $this->methodName, $this->arguments);
    }
}
