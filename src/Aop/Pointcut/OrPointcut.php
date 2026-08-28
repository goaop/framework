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
 * Logical "or" filter.
 */
final readonly class OrPointcut implements Pointcut
{
    /**
     * Kind of filter
     */
    private int $pointcutKind;

    /**
     * List of Pointcut to combine
     *
     * @var array<Pointcut>
     */
    private array $pointcuts;

    /**
     * Or constructor
     */
    public function __construct(Pointcut ...$pointcuts)
    {
        $pointcutKind = 0;
        foreach ($pointcuts as $singlePointcut) {
            $pointcutKind |= $singlePointcut->getKind();
        }
        $this->pointcutKind  = $pointcutKind;
        $this->pointcuts     = $pointcuts;
    }

    #[\Override]
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null
    ): bool {
        return array_any(
            $this->pointcuts,
            fn(Pointcut $singlePointcut): bool => $singlePointcut->matches($context, $reflector)
        );
    }

    #[\Override]
    public function getKind(): int
    {
        return $this->pointcutKind;
    }
}
