<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use ReflectionClass;
use Go\Aop\PointFilter;

/**
 * Inheritance class matcher that match single class name or any subclass
 */
class InheritanceClassFilter implements PointFilter
{
    /**
     * Parent class or interface name to match in hierarchy
     */
    protected string $parentClass;

    /**
     * Inheritance class matcher constructor
     */
    public function __construct(string $parentClassName)
    {
        $this->parentClass = $parentClassName;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $class Specific part of code, can be any Reflection class
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     */
    public function matches($class, $context = null, $instance = null, array $arguments = null): bool
    {
        if (!$class instanceof ReflectionClass) {
            return false;
        }

        return $class->isSubclassOf($this->parentClass) || \in_array($this->parentClass, $class->getInterfaceNames());
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return self::KIND_CLASS;
    }
}
