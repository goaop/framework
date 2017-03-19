<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use ReflectionFunction;

/**
 * Namespaced version of global functions
 */
class NamespacedReflectionFunction extends ReflectionFunction
{
    /**
     * Custom namespace name
     *
     * @var string
     */
    private $namespace = '';

    /**
     * Extends the logic with passing the namespace name
     *
     * @param string $namespace Name of the namespace
     * {@inheritDoc}
     */
    public function __construct($name, $namespace = '')
    {
        $this->namespace = $namespace;
        parent::__construct($name);
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaceName()
    {
        if ($this->namespace) {
            return $this->namespace;
        }

        return parent::getNamespaceName();
    }
}
