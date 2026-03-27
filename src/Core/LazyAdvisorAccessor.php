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

namespace Go\Core;

use AllowDynamicProperties;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Intercept\Interceptor;
use InvalidArgumentException;

/**
 * Provides an interface for loading of advisors from the container
 */
#[AllowDynamicProperties]
final class LazyAdvisorAccessor
{
    /**
     * Accessor constructor
     */
    public function __construct(
        protected readonly AspectContainer $container,
        protected readonly AspectLoader $loader
    ) {}

    /**
     * Returns the Interceptor for the given advisor name, loading and caching it on first access.
     *
     * Prefer this over the magic property accessor when the name is a variable — PHP's `__get()` is
     * identical in behaviour, but static-analysis tools cannot track its return type for variable keys.
     *
     * @throws InvalidArgumentException if referenced value is not an advisor or its advice is not an Interceptor
     */
    public function getInterceptor(string $name): Interceptor
    {
        return $this->__get($name);
    }

    /**
     * Magic advice accessor
     *
     * @throws InvalidArgumentException if referenced value is not an advisor or its advice is not an Interceptor
     */
    public function __get(string $name): Interceptor
    {
        if (!$this->container->has($name)) {
            [$aspectName] = explode('->', $name, 2);
            if (!is_subclass_of($aspectName, Aspect::class)) {
                throw new AspectException("{$aspectName} is not a valid aspect class");
            }
            $aspectInstance = $this->container->getService($aspectName);
            $this->loader->loadAndRegister($aspectInstance);

        }
        $advisor = $this->container->getValue($name);
        if (!$advisor instanceof Advisor) {
            throw new InvalidArgumentException("Reference {$name} is not an advisor");
        }
        $advice = $advisor->getAdvice();
        if (!$advice instanceof Interceptor) {
            throw new InvalidArgumentException("Advice {$name} is not an Interceptor");
        }
        $this->$name = $advice;

        return $this->$name;
    }
}
