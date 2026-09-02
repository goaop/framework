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

namespace Go\Aop\Support;

use Go\Aop\Advice;
use Go\Aop\CompilableToPhp;
use Go\Aop\Pointcut;
use Go\Aop\PointcutAdvisor;
use Go\Core\NotCompilableException;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;

/**
 * Convenient Pointcut-driven Advisor implementation.
 *
 * This is the most commonly used Advisor implementation. It can be used with any pointcut and advice type,
 * including introductions.
 */
final readonly class GenericPointcutAdvisor implements PointcutAdvisor, CompilableToPhp
{
    public function __construct(private Pointcut $pointcut, private Advice $advice) {}

    public function getAdvice(): Advice
    {
        return $this->advice;
    }

    public function getPointcut(): Pointcut
    {
        return $this->pointcut;
    }

    public function compileToPhp(): Expr
    {
        if (!$this->pointcut instanceof CompilableToPhp || !$this->advice instanceof CompilableToPhp) {
            $notCompilable = $this->pointcut instanceof CompilableToPhp ? $this->advice : $this->pointcut;

            throw new NotCompilableException(
                'Cannot compile an instance of ' . get_debug_type($notCompilable) . ' into plain PHP',
            );
        }

        return new New_(new FullyQualified(self::class), [
            new Arg($this->pointcut->compileToPhp()),
            new Arg($this->advice->compileToPhp()),
        ]);
    }
}
