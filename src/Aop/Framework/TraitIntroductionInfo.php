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
     * List of interfaces to introduce
     *
     * @var array
     */
    private $introducedInterfaces;

    /**
     * List of traits to include
     *
     * @var array
     */
    private $introducedTraits;

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
     *
     * @param string|string[] $introducedInterfaces List of introduced interfaces
     * @param string|string[] $introducedTraits List of introduced traits
     */
    public function __construct($introducedInterfaces, $introducedTraits)
    {
        $this->introducedInterfaces = (array) $introducedInterfaces;
        $this->introducedTraits     = (array) $introducedTraits;
    }

    /**
     * Return the additional interfaces introduced by this Advisor or Advice.
     *
     * @return array|string[] introduced interfaces
     */
    public function getInterfaces() : array
    {
        return $this->introducedInterfaces;
    }

    /**
     * Return the list of traits with realization of introduced interfaces
     *
     * @return array|string[] trait implementations
     */
    public function getTraits() : array
    {
        return $this->introducedTraits;
    }
}
