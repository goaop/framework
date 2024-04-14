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
use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use InvalidArgumentException;

/**
 * Provides an interface for loading of advisors from the container
 */
#[AllowDynamicProperties]
class LazyAdvisorAccessor
{
    /**
     * Instance of aspect container
     */
    protected AspectContainer $container;

    /**
     * Aspect loader instance
     */
    protected AspectLoader $loader;

    /**
     * Accessor constructor
     */
    public function __construct(AspectContainer $container, AspectLoader $loader)
    {
        $this->container = $container;
        $this->loader    = $loader;
    }

    /**
     * Magic advice accessor
     *
     * @throws InvalidArgumentException if referenced value is not an advisor
     */
    public function __get(string $name): Advice
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
        $this->$name = $advisor->getAdvice();

        return $this->$name;
    }
}
