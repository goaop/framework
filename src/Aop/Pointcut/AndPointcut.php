<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\Support\AndPointFilter;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Logical "AND" pointcut that combines two simple pointcuts
 */
class AndPointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * First pointcut
     */
    protected Pointcut $first;

    /**
     * Second pointcut
     */
    protected Pointcut $second;

    /**
     * Returns pointcut kind
     */
    protected int $kind;

    /**
     * "And" pointcut constructor
     */
    public function __construct(Pointcut $first, Pointcut $second)
    {
        $this->first  = $first;
        $this->second = $second;
        $this->kind   = $first->getKind() & $second->getKind();

        $this->classFilter = new AndPointFilter($first->getClassFilter(), $second->getClassFilter());
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed              $point     Specific part of code, can be any Reflection class
     * @param null|mixed         $context   Related context, can be class or namespace
     * @param null|string|object $instance  Invocation instance or string for static calls
     * @param null|array         $arguments Dynamic arguments for method
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        return $this->matchPart($this->first, $point, $context, $instance, $arguments)
            && $this->matchPart($this->second, $point, $context, $instance, $arguments);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return $this->kind;
    }

    /**
     * Checks if point filter matches the point
     *
     * @param Pointcut                                            $pointcut
     * @param ReflectionMethod|ReflectionProperty|ReflectionClass $point
     * @param mixed                                               $context   Related context, can be class or namespace
     * @param object|string|null                                  $instance  [Optional] Instance for dynamic matching
     * @param array|null                                          $arguments [Optional] Extra arguments for dynamic
     *                                                                       matching
     *
     * @return bool
     */
    protected function matchPart(
        Pointcut $pointcut,
        $point,
        $context = null,
        $instance = null,
        array $arguments = null
    ): bool {
        return $pointcut->matches($point, $context, $instance, $arguments)
            && $pointcut->getClassFilter()->matches($context);
    }
}
