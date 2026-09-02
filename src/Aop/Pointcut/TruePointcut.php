<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Core\Cache\AdvisorCacheCompiler;
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
 * Canonical Pointcut instance that always matches.
 */
final readonly class TruePointcut implements Pointcut
{
    /**
     * Default constructor can be used to specify concrete pointcut kind
     */
    public function __construct(private int $pointcutKind = self::KIND_ALL) {}

    /**
     * @inheritdoc
     * @return true Covariant, always true for TruePointcut
     */
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null,
    ): true {
        return true;
    }

    public function getKind(): int
    {
        return $this->pointcutKind;
    }

    public function compileToPhp(): Expr
    {
        return new New_(new FullyQualified(self::class), AdvisorCacheCompiler::compileArgs([
            ['pointcutKind', new Int_($this->pointcutKind), $this->pointcutKind === self::KIND_ALL],
        ]));
    }
}
