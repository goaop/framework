<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Go\PhpUnit;

/**
 * Value object class for join point constraints.
 */
final class JoinPoint
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $joinPoint;

    /**
     * @var bool
     */
    private $static;

    /**
     * @var null|int
     */
    private $index;

    public function __construct($class, $method, $joinPoint, $static = false, $index = null)
    {
        $this->class = $class;
        $this->method = $method;
        $this->joinPoint = $joinPoint;
        $this->static = $static;
        $this->index = $index;

        if (null !== $index && $index < 0) {
            throw new \InvalidArgumentException(sprintf('Expected "NULL" or integer greater or equal to 0, got "%s".', $index));
        }
    }

    /**
     * Get full qualified class name.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Get method name.
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Get join point expression.
     *
     * @return string
     */
    public function getJoinPoint()
    {
        return $this->joinPoint;
    }

    /**
     * Is method static.
     *
     * @return bool
     */
    public function isStatic()
    {
        return $this->static;
    }

    /**
     * Get join point ordering index.
     *
     * @return int|null
     */
    public function getIndex()
    {
        return $this->index;
    }
}
