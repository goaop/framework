<?php

declare(strict_types=1);
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
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Logical "and" pointcut filter.
 */
final readonly class AndPointcut implements Pointcut
{
    public int $kind;

    /**
     * List of Pointcut to combine with "AND"
     *
     * @var array<Pointcut>
     */
    private array $pointcuts;

    /**
     * And constructor
     */
    public function __construct(?int $kind = null, Pointcut ...$pointcuts)
    {
        // If we don't have specified kind, it will be calculated as intersection then
        if (!isset($kind)) {
            $kind = -1;
            foreach ($pointcuts as $singlePointcut) {
                $kind &= $singlePointcut->kind;
            }
        }
        $this->kind      = $kind;
        $this->pointcuts = $pointcuts;
    }

    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        return array_all(
            $this->pointcuts,
            fn(Pointcut $singlePointcut): bool => $singlePointcut->matches($context, $reflector),
        );
    }

    public function compileToPhp(): Expr
    {
        $args = [new Arg(new Int_($this->kind))];
        foreach ($this->pointcuts as $singlePointcut) {
            $args[] = new Arg($singlePointcut->compileToPhp());
        }

        return new New_(new FullyQualified(self::class), $args);
    }
}
