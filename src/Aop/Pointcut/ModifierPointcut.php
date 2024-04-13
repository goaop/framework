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
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * ModifierPointcut performs matching on modifiers for reflector
 */
final class ModifierPointcut implements Pointcut
{
    /**
     * Bit mask, that should be always match
     */
    private int $andMask;

    /**
     * Bit mask, that can be used for additional check
     */
    private int $orMask = 0;

    /**
     * Bit mask to exclude specific value from matching, for example, !public
     */
    private int $notMask = 0;

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
     * @return ($reflector is null ? true : bool)
     */
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // With context only we always match, as we don't know about modifiers of given reflector
        if (!isset($reflector)) {
            return true;
        }

        // Only ReflectionFunction doesn't have getModifiers method
        if ($reflector instanceof ReflectionFunction) {
            $modifiers = 0;
        } else {
            $modifiers = $reflector->getModifiers();
        }

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

    public function getKind(): int
    {
        return Pointcut::KIND_ALL;
    }
}
