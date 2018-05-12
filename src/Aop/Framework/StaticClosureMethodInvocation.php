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

use Closure;

/**
 * Static closure method invocation is responsible to call static methods via closure
 */
final class StaticClosureMethodInvocation extends AbstractMethodInvocation
{
    /**
     * Closure to use
     *
     * @var Closure
     */
    protected $closureToCall;

    /**
     * Previous scope of invocation
     *
     * @var null|string
     */
    protected $previousScope;

    /**
     * Proceeds all registered advices for the static method and returns an invocation result
     */
    public function proceed()
    {
        if (isset($this->advices[$this->current])) {
            $currentInterceptor = $this->advices[$this->current++];

            return $currentInterceptor->invoke($this);
        }

        // Rebind the closure if scope (class name) was changed since last time
        if ($this->previousScope !== $this->instance) {
            if ($this->closureToCall === null) {
                $this->closureToCall = static::getStaticInvoker($this->className, $this->reflectionMethod->name);
            }
            $this->closureToCall = $this->closureToCall->bindTo(null, $this->instance);
            $this->previousScope = $this->instance;
        }

        return ($this->closureToCall)($this->arguments);

    }

    /**
     * Returns static method invoker for the concrete method in the class
     */
    protected static function getStaticInvoker(string $className, string $methodName): Closure
    {
        return function (array $args) use ($className, $methodName) {
            return forward_static_call_array([$className, $methodName], $args);
        };
    }
}
