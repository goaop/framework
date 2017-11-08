<?php
declare(strict_types = 1);
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
 * Advice for introduction that holds list of traits and interfaces for the concrete class
 */
class TraitIntroductionInfo implements IntroductionInfo
{
    /**
     * Introduced interface
     *
     * @var string
     */
    private $introducedInterface;

    /**
     * Trait to use
     *
     * @var string
     */
    private $introducedTrait;

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
     *
     * @param string $introducedTrait Introduced trait
     * @param string $introducedInterface Introduced interface
     */
    public function __construct(string $introducedTrait, string $introducedInterface)
    {
        $this->introducedTrait     = $introducedTrait;
        $this->introducedInterface = $introducedInterface;
    }

    /**
     * Returns the additional interface introduced by this Advisor or Advice.
     */
    public function getInterface(): string
    {
        return $this->introducedInterface;
    }

    /**
     * Returns the additional trait with realization of introduced interface
     */
    public function getTrait(): string
    {
        return $this->introducedTrait;
    }
}
