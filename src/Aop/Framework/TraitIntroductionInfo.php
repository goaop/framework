<?php
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
    public function __construct($introducedTrait, $introducedInterface)
    {
        $this->introducedTrait     = $introducedTrait;
        $this->introducedInterface = $introducedInterface;
    }

    /**
     * Return the additional interface introduced by this Advisor or Advice.
     *
     * @return string The introduced interface or empty
     */
    public function getInterface()
    {
        return $this->introducedInterface;
    }

    /**
     * Return the additional trait with realization of introduced interface
     *
     * @return string The trait name to use or empty
     */
    public function getTrait()
    {
        return $this->introducedTrait;
    }
}
