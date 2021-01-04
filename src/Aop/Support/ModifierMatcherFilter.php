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
     */
    protected int $andMask = 0;

    /**
     * Bit mask, that can be used for additional check
     */
    protected int $orMask = 0;

    /**
     * Bit mask to exclude specific value from matching, for example, !public
     */
    protected int $notMask = 0;

    /**
     * Initialize default filter with "and" mask
     *
     * @param int $initialMask Default mask for "and"
     */
    public function __construct(int $initialMask = 0)
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
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        $modifiers = $point->getModifiers();

        return !($this->notMask & $modifiers) &&
            (($this->andMask === ($this->andMask & $modifiers)) || ($this->orMask & $modifiers));
    }

    /**
     * Add "and" mask
     */
    public function andMatch(int $bitMask): self
    {
        $this->andMask |= $bitMask;

        return $this;
    }

    /**
     * Add "or" mask
     */
    public function orMatch(int $bitMask): self
    {
        $this->orMask |= $bitMask;

        return $this;
    }

    /**
     * Add "not" mask
     */
    public function notMatch(int $bitMask): self
    {
        $this->notMask |= $bitMask;

        return $this;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return self::KIND_ALL;
    }
}
