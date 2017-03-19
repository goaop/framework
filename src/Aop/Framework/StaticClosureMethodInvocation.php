<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

/**
 * Static closure method invocation is responsible to call static methods via closure
 */
final class StaticClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     *
     * @var \Closure
     */
    protected $closureToCall = null;

    /**
     * Previous scope of invocation
     *
     * @var null|object|string
     */
    protected $previousScope = null;

    /**
     * Invokes original method and return result from it
     *
     * @return mixed
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            /** @var $currentInterceptor \Go\Aop\Intercept\Interceptor */
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Rebind the closure if scope (class name) was changed since last time
        if ($this->previousScope !== $this->instance) {
            if ($this->closureToCall === null) {
                $this->closureToCall = $this->getStaticInvoker($this->className, $this->reflectionMethod->name);
            }
            $this->closureToCall = $this->closureToCall->bindTo(null, $this->instance);
            $this->previousScope = $this->instance;
        }

        $closureToCall = $this->closureToCall;

        return $closureToCall($this->arguments);

    }

    /**
     * Returns static method invoker
     *
     * @param string $className Class name to forward request
     * @param string $method Method name to call
     *
     * @return \Closure
     */
    protected static function getStaticInvoker($className, $method)
    {
        return function(array $args) use ($className, $method) {
            return forward_static_call_array([$className, $method], $args);
        };
    }
}
