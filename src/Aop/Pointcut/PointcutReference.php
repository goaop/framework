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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\String_;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Reference to the pointcut holds an id of pointcut to fetch when needed
 *
 * @internal Framework implementation detail of pointcut parsing and caching, free to change between releases
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

    #[\Override]
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        return $this->getPointcut()->matches($context, $reflector);
    }

    #[\Override]
    public function getKind(): int
    {
        return $this->getPointcut()->getKind();
    }

    #[\Override]
    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class), [
            new Arg(new String_($this->pointcutId)),
        ]);
    }

    /**
     * Returns a real pointcut from the container
     */
    private function getPointcut(): Pointcut
    {
        if (!isset($this->pointcut)) {
            $pointcutValue = $this->container->getValue($this->pointcutId);
            if (!$pointcutValue instanceof Pointcut) {
                throw new AspectException("Pointcut reference {$this->pointcutId} resolves to " . get_debug_type($pointcutValue) . ', not to a pointcut');
            }
            $this->pointcut = $pointcutValue;
        }

        return $this->pointcut;
    }
}
