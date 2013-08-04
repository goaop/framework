<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
