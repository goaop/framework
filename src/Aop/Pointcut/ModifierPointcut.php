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

use Go\Aop\CompilableToPhp;
use Go\Aop\Pointcut;
use Go\Core\AdvisorCacheCompiler;
use Go\ParserReflection\ReflectionFileNamespace;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Int_;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * ModifierPointcut performs matching on modifiers for reflector
 *
 * Matching is bitmask-based on {@see \ReflectionMethod::getModifiers()} /
 * {@see \ReflectionProperty::getModifiers()}. Besides the classic visibility masks
 * (public/protected/private/static/final), property-only masks are supported:
 *
 *  - {@see \ReflectionProperty::IS_READONLY} — 'readonly' grammar predicate
 *  - {@see \ReflectionProperty::IS_PRIVATE_SET} — 'private(set)' grammar predicate (PHP 8.4+)
 *  - {@see \ReflectionProperty::IS_PROTECTED_SET} — 'protected(set)' grammar predicate (PHP 8.4+)
 *
 * Both native reflection and Go\ParserReflection\ReflectionProperty expose these bits via
 * getModifiers(), so no reflection-implementation-specific guards are needed here. Methods
 * never carry these bits, so such predicates simply never match method reflectors.
 */
final readonly class ModifierPointcut implements Pointcut, CompilableToPhp
{
    /**
     * Initialize the filter with pre-resolved bit masks
     *
     * @param int $andMask Bit mask that should always match
     * @param int $orMask  Bit mask that can be used for additional check
     * @param int $notMask Bit mask to exclude specific value from matching, for example, !public
     */
    public function __construct(
        private int $andMask = 0,
        private int $orMask = 0,
        private int $notMask = 0,
    ) {}

    /**
     * @return ($reflector is null ? true : bool)
     */
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
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

        return !($this->notMask & $modifiers)
            && (($this->andMask === ($this->andMask & $modifiers)) || ($this->orMask & $modifiers));
    }

    /**
     * Returns a new filter with the given bits added to the "and" mask
     */
    public function andMatch(int $bitMask): self
    {
        return new self($this->andMask | $bitMask, $this->orMask, $this->notMask);
    }

    /**
     * Returns a new filter with the given bits added to the "or" mask
     */
    public function orMatch(int $bitMask): self
    {
        return new self($this->andMask, $this->orMask | $bitMask, $this->notMask);
    }

    /**
     * Returns a new filter with the given bits added to the "not" mask
     */
    public function notMatch(int $bitMask): self
    {
        return new self($this->andMask, $this->orMask, $this->notMask | $bitMask);
    }

    public function getKind(): int
    {
        return Pointcut::KIND_ALL;
    }

    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class), AdvisorCacheCompiler::compileArgs([
            ['andMask', new Int_($this->andMask), $this->andMask === 0],
            ['orMask', new Int_($this->orMask), $this->orMask === 0],
            ['notMask', new Int_($this->notMask), $this->notMask === 0],
        ]));
    }
}
