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

namespace Go\Aop\Pointcut;

use ReflectionClass;
use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use ReflectionMethod;

/**
 * Flow pointcut is a dynamic checker that verifies stack trace to understand is it matches or not
 */
class CFlowBelowMethodPointcut implements PointFilter, Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * Filter for the class
     *
     * @var null|PointFilter
     */
    protected $internalClassFilter = null;

    /**
     * Filter for the points
     *
     * @var null|PointFilter
     */
    protected $internalPointFilter;

    /**
     * Control flow below constructor
     *
     * @param Pointcut $pointcut Instance of pointcut, that will be used for matching
     * @throws \InvalidArgumentException if filter doesn't support methods
     */
    public function __construct(Pointcut $pointcut)
    {
        $this->internalClassFilter = $pointcut->getClassFilter();
        $this->internalPointFilter = $pointcut;
        if (!($this->internalPointFilter->getKind() & PointFilter::KIND_METHOD)) {
            throw new \InvalidArgumentException("Only method filters are valid for control flow");
        }
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     * @param null|mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null) : bool
    {
        // With single parameter (statically) always matches
        if (!$instance) {
            return true;
        }

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $stackFrame) {
            if (!isset($stackFrame['class'])) {
                continue;
            }
            $refClass = new ReflectionClass($stackFrame['class']);
            if (!$this->internalClassFilter->matches($refClass)) {
                continue;
            }
            $refMethod = new ReflectionMethod($stackFrame['class'], $stackFrame['function']);
            if ($this->internalPointFilter->matches($refMethod)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind() : int
    {
        return PointFilter::KIND_METHOD | PointFilter::KIND_DYNAMIC;
    }
}
