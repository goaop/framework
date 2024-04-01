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

use Go\Aop\IntroductionInfo;

/**
 * Advice for introduction that holds trait and interface for the concrete class
 */
readonly class TraitIntroductionInfo implements IntroductionInfo
{
    /**
     * Creates a TraitIntroductionInfo with given trait name and interface name.
     */
    public function __construct(
        private string $introducedTrait,
        private string $introducedInterface
    ){}

    public function getInterface(): string
    {
        return $this->introducedInterface;
    }

    public function getTrait(): string
    {
        return $this->introducedTrait;
    }
}
