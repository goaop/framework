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
     * Pointcut reference constructor
     *
     * @param string $pointcutId Name of the pointcut to fetch from the container
     */
    public function __construct(
        private AspectContainer $container,
        private readonly string $pointcutId
    ) {}

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        return $this->getPointcut()->matches($context, $reflector, $instanceOrScope, $arguments);
    }

    public function getKind(): int
    {
        return $this->getPointcut()->getKind();
    }

    public function __sleep(): array
    {
        return ['pointcutId'];
    }

    public function __wakeup(): void
    {
        $this->container = AspectKernel::getInstance()->getContainer();
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
