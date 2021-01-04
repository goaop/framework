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

use Go\Aop\Advice;
use Go\Aop\Advisor;
use InvalidArgumentException;

/**
 * Provides an interface for loading of advisors from the container
 */
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
        if ($this->container->has($name)) {
            $advisor = $this->container->get($name);
        } else {
            list(, $advisorName) = explode('.', $name);
            list($aspect)        = explode('->', $advisorName);
            $aspectInstance      = $this->container->getAspect($aspect);
            $this->loader->loadAndRegister($aspectInstance);

            $advisor = $this->container->get($name);
        }

        if (!$advisor instanceof Advisor) {
            throw new InvalidArgumentException("Reference {$name} is not an advisor");
        }
        $this->$name = $advisor->getAdvice();

        return $this->$name;
    }
}
