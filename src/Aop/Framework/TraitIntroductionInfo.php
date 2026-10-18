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
use Go\Aop\IntroductionInfo;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name\FullyQualified;

/**
 * Advice for introduction that holds trait and interface for the concrete class
 */
final readonly class TraitIntroductionInfo implements IntroductionInfo
{
    /**
     * Creates a TraitIntroductionInfo with given trait name and interface name.
     */
    public function __construct(
        /** @var trait-string */
        public string $trait,
        /** @var class-string */
        public string $interface,
    ) {}

    public function getType(): AdviceTypeEnum
    {
        return AdviceTypeEnum::Introduction;
    }

    public function compileToPhp(): Expr
    {
        // Trait and interface names come from existing code, so ::class fetches are always safe
        return new New_(new FullyQualified(self::class), [
            new Arg(new ClassConstFetch(new FullyQualified($this->trait), 'class')),
            new Arg(new ClassConstFetch(new FullyQualified($this->interface), 'class')),
        ]);
    }
}
