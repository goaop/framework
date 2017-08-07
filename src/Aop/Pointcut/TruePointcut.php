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

use Go\Aop\Pointcut;

/**
 * Canonical Pointcut instance that always matches.
 */
class TruePointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * Filter kind
     *
     * @var int
     */
    protected $filterKind;

    /**
     * Default constructor can be used to specify concrete filter kind
     *
     * @param int $filterKind Kind of filter, e.g. KIND_METHOD
     */
    public function __construct(int $filterKind = self::KIND_ALL)
    {
        $this->filterKind = $filterKind;
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
        return true;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind() : int
    {
        return $this->filterKind;
    }
}
