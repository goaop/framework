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
     */
    private string $namespace;

    /**
     * Extends the logic with passing the namespace name
     *
     * {@inheritDoc}
     */
    public function __construct(string $functionName, string $namespaceName = '')
    {
        $this->namespace = $namespaceName;
        parent::__construct($functionName);
    }

    /**
     * {@inheritDoc}
     */
    public function getNamespaceName(): string
    {
        if (!empty($this->namespace)) {
            return $this->namespace;
        }

        return parent::getNamespaceName();
    }
}
