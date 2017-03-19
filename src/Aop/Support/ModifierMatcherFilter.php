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

use Go\Aop\PointFilter;

/**
 * ModifierMatcherFilter performs checks on modifiers for reflection point
 */
class ModifierMatcherFilter implements PointFilter
{

    /**
     * Bit mask, that should be always match
     *
     * @var int
     */
    protected $andMask = 0;

    /**
     * Bit mask, that can be used for additional check
     *
     * @var int
     */
    protected $orMask = 0;

    /**
     * Bit mask to exclude specific value from matching, for example, !public
     *
     * @var int
     */
    protected $notMask = 0;

    /**
     * Initialize default filter with "and" mask
     *
     * @param int $initialMask Default mask for "and"
     */
    public function __construct($initialMask = 0)
    {
        $this->andMask = $initialMask;
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
        $modifiers = $point->getModifiers();

        return !($this->notMask & $modifiers) &&
            (($this->andMask === ($this->andMask & $modifiers)) || ($this->orMask & $modifiers));
    }

    /**
     * Add "and" or mask
     *
     * @param integer $bitMask
     * @return $this
     */
    public function andMatch($bitMask)
    {
        $this->andMask |= $bitMask;

        return $this;
    }

    /**
     * Add "or" mask
     *
     * @param integer $bitMask
     * @return $this
     */
    public function orMatch($bitMask)
    {
        $this->orMask |= $bitMask;

        return $this;
    }

    /**
     * Add "not" mask
     *
     * @param integer $bitMask
     * @return $this
     */
    public function notMatch($bitMask)
    {
        $this->notMask |= $bitMask;

        return $this;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_ALL;
    }

    /**
     * Merges current one filter with another one and returns a new combined filter
     *
     * @param ModifierMatcherFilter $filter
     *
     * @return ModifierMatcherFilter
     */
    public function merge(ModifierMatcherFilter $filter)
    {
        $newFilter = clone $this;
        $newFilter->andMask |= $filter->andMask;
        $newFilter->orMask  |= $filter->orMask;
        $newFilter->notMask |= $filter->notMask;

        return $newFilter;
    }
}
