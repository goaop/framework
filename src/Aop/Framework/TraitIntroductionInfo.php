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

namespace Go\Aop\Framework;

use Go\Aop\AdviceTypeEnum;
use Go\Aop\CompilableToPhp;
use Go\Aop\IntroductionInfo;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;

/**
 * Advice for introduction that holds trait and interface for the concrete class
 */
final readonly class TraitIntroductionInfo implements IntroductionInfo, CompilableToPhp
{
    /**
     * Creates a TraitIntroductionInfo with given trait name and interface name.
     *
     * @param trait-string $introducedTrait     Trait name
     * @param class-string $introducedInterface Interface name
     */
    public function __construct(
        private string $introducedTrait,
        private string $introducedInterface,
    ) {}

    public function getInterface(): string
    {
        return $this->introducedInterface;
    }

    public function getTrait(): string
    {
        return $this->introducedTrait;
    }

    public function getType(): AdviceTypeEnum
    {
        return AdviceTypeEnum::Introduction;
    }

    public function compileToPhp(): Expr
    {
        // Trait and interface names come from existing code, so ::class fetches are always safe
        return new New_(new FullyQualified(self::class), [
            new Arg(new ClassConstFetch(new FullyQualified($this->introducedTrait), 'class')),
            new Arg(new ClassConstFetch(new FullyQualified($this->introducedInterface), 'class')),
        ]);
    }
}
