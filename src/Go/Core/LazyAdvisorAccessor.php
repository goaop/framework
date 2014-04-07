<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */


namespace Go\Core;

/**
 * Provides an interface for loading of advisors from the container
 */
class LazyAdvisorAccessor
{
    /**
     * @var AspectContainer|Container
     */
    protected $container;

    /**
     * Aspect loader instance
     *
     * @var AspectLoader
     */
    protected $loader;

    /**
     * Accessor constructor
     *
     * @param AspectContainer $container
     * @param AspectLoader $loader
     */
    public function __construct(AspectContainer $container, AspectLoader $loader)
    {
        $this->container = $container;
        $this->loader    = $loader;
    }

    /**
     * Magic accessor
     *
     * @param string $name Key name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->container->has($name)) {
            return $this->container->get($name);
        } else {
            list(, $advisorName) = explode('.', $name);
            list($aspect)        = explode('->', $advisorName);
            $aspectInstance      = $this->container->getAspect($aspect);
            $this->loader->load($aspectInstance);

            return $this->container->get($name);
        }
    }
}
