<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Pointcut that matches methods and functions returning by reference (`function &name()`)
 *
 * Such methods can not be intercepted, because joinpoints return values and the reference would be lost:
 * weaving one fails with an error. Exclude them from a broad pointcut with `&& !matchReturningByReference()`.
 */
final class MatchReturningByReferencePointcut implements Pointcut
{
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): bool {
        // With only one context given, we should always match, as we need more info about nested items
        if (!isset($reflector)) {
            return true;
        }

        return ($reflector instanceof ReflectionMethod || $reflector instanceof ReflectionFunction)
            && $reflector->returnsReference();
    }

    public function getKind(): int
    {
        return Pointcut::KIND_METHOD | Pointcut::KIND_FUNCTION;
    }

    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class));
    }
}
