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

namespace Go\Aop;

/**
 * Filter that restricts matching of a pointcut or introduction to a given set of reflection points.
 *
 * A PointFilter may be evaluated statically or at runtime (dynamically).
 *
 * Static matching involves point and context. Dynamic matching also provides an instance and arguments for
 * a particular invocation.
 *
 * If point filter is not dynamic (self::KIND_DYNAMIC), evaluation can be performed statically,
 * and the result will be the same for all invocations of this joinpoint, whatever their arguments.
 *
 * If an implementation returns true from its 2-arg matches() method and filter is self::KIND_DYNAMIC,
 * the 3-arg matches() method will be invoked immediately before each potential execution of the related advice,
 * to decide whether the advice should run. All previous advice, such as earlier interceptors in an interceptor chain,
 * will have run, so any state changes they have produced in parameters will be available at the time of evaluation.
 */
interface PointFilter
{

    const KIND_METHOD      = 1;
    const KIND_PROPERTY    = 2;
    const KIND_CLASS       = 4;
    const KIND_TRAIT       = 8;
    const KIND_FUNCTION    = 16;
    const KIND_INIT        = 32;
    const KIND_STATIC_INIT = 64;
    const KIND_ALL         = 127;
    const KIND_DYNAMIC     = 256;

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
    public function matches($point, $context = null, $instance = null, array $arguments = null);

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind();
}
