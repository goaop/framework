<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\AspectException;
use Go\Aop\Pointcut;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reference to the pointcut holds an id of pointcut to fetch when needed
 */
final class PointcutReference implements Pointcut
{
    private ?Pointcut $pointcut = null;

    /**
     * Aspect container, resolved from the kernel on first access and memoized in the backing store
     */
    private AspectContainer $container {
        get => $this->container ??= AspectKernel::getInstance()->getContainer();
    }

    /**
     * Pointcut reference constructor
     *
     * @param string $pointcutId Name of the pointcut to fetch from the container
     */
    public function __construct(private readonly string $pointcutId) {}

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        return $this->getPointcut()->matches($context, $reflector);
    }

    public function getKind(): int
    {
        return $this->getPointcut()->getKind();
    }

    /**
     * Returns a real pointcut from the container
     */
    private function getPointcut(): Pointcut
    {
        if (!isset($this->pointcut)) {
            $pointcutValue = $this->container->getValue($this->pointcutId);
            if (!$pointcutValue instanceof Pointcut) {
                throw new AspectException("Reference {$this->pointcutId} points not to a Pointcut.");
            }
            $this->pointcut = $pointcutValue;
        }

        return $this->pointcut;
    }
}
