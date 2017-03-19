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

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\OrPointFilter;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class OrPointcut extends AndPointcut
{

    /**
     * Signature method matcher constructor
     *
     * @param Pointcut $first First filter
     * @param Pointcut $second Second filter
     */
    public function __construct(Pointcut $first, Pointcut $second)
    {
        $this->first  = $first;
        $this->second = $second;
        $this->kind   = $first->getKind() | $second->getKind();

        $this->classFilter = new OrPointFilter($first->getClassFilter(), $second->getClassFilter());
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
    public function matches($point, $context = null, $instance = null, array $arguments = null)
    {
        return $this->matchPart($this->first, $point, $context, $instance, $arguments)
            || $this->matchPart($this->second, $point, $context, $instance, $arguments);
    }

    /**
     * @inheritDoc
     */
    protected function matchPart(Pointcut $pointcut, $point, $context = null, $instance = null, array $arguments = null)
    {
        $pointcutKind = $pointcut->getKind();
        // We need to recheck filter kind one more time, because of OR syntax
        switch (true) {
            case ($point instanceof \ReflectionMethod && ($pointcutKind & PointFilter::KIND_METHOD)):
            case ($point instanceof \ReflectionProperty && ($pointcutKind & PointFilter::KIND_PROPERTY)):
            case ($point instanceof \ReflectionClass && ($pointcutKind & PointFilter::KIND_CLASS)):
                return parent::matchPart($pointcut, $point, $context, $instance, $arguments);

            default:
                return false;
        }
    }
}
