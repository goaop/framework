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
     * Previous instance to call
     *
     * @var null
     */
    private $previousInstance = null;

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

        // Fill the closure only once if it's empty
        if (!$this->closureToCall) {
            $this->closureToCall = $this->reflectionMethod->getClosure($this->instance);
        }

        $closureToCall = $this->closureToCall;

        // Rebind the closure if instance was changed since last time
        if ($this->previousInstance !== $this->instance) {
            // Fastest way to check that $this->instance is string or object
            if ($this->instance !== (object) $this->instance) {
                $closureToCall = $closureToCall->bindTo(null, $this->instance);
            } else {
                $closureToCall = $closureToCall->bindTo($this->instance, $this->classOrObject);
            }
            $this->closureToCall    = $closureToCall;
            $this->previousInstance = $this->instance;
        }

        $args = $this->arguments;
        switch(count($args)) {
            case 0:
                return $closureToCall();
            case 1:
                return $closureToCall($args[0]);
            case 2:
                return $closureToCall($args[0], $args[1]);
            case 3:
                return $closureToCall($args[0], $args[1], $args[2]);
            case 4:
                return $closureToCall($args[0], $args[1], $args[2], $args[3]);
            case 5:
                return $closureToCall($args[0], $args[1], $args[2], $args[3], $args[4]);
            default:
                return call_user_func_array($closureToCall, $args);
        }

    }
}
