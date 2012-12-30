<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Intercept\MethodInterceptor;

/**
 * NB: This invocation is only used for static methods to support LSB
 *
 * @package go
 */
class ClosureStaticMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     *
     * @var \Closure
     */
    private $closureToCall = null;

    /**
     * Previous scope of invocation
     *
     * @var null
     */
    private $previousScope = null;

    /**
     * {@inheritdoc}
     */
    public function __construct($classNameOrObject, $methodName, array $advices)
    {
        parent::__construct($classNameOrObject, $methodName, $advices);
        $this->closureToCall = $this->getStaticInvoker($this->parentClass, $methodName);
    }

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor MethodInterceptor */
            $currentInterceptor = $this->advices[$this->current++];
            return $currentInterceptor->invoke($this);
        }

        // Rebind the closure if scope (class name) was changed since last time
        if ($this->previousScope !== $this->instance) {
            $this->closureToCall = $this->closureToCall->bindTo(null, $this->instance);
            $this->previousScope = $this->instance;
        }

        $closureToCall = $this->closureToCall;

        return $closureToCall($this->arguments);

    }

    /**
     * Returns static method invoker
     *
     * @param string $parent Parent class name to forward request
     * @param string $method Method name to call
     *
     * @return callable
     */
    protected static function getStaticInvoker($parent, $method)
    {
        return function (array $args) use ($parent, $method) {
            return forward_static_call_array(array($parent, $method), $args);
        };
    }
}
