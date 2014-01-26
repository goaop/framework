<?php
/**
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
 * @package go
 */
class TraitIntroductionInfo implements IntroductionInfo
{

    /**
     * Name of the interface to introduce
     *
     * @var string
     */
    private $introducedInterface;

    /**
     * Name of the class with implementation (trait)
     *
     * @var string
     */
    private $implementationClass;

    /**
     * Create a DefaultIntroductionAdvisor for the given advice.
     */
    public function __construct($interfaceType, $implementationClass)
    {
        $this->introducedInterface = $interfaceType;
        $this->implementationClass = $implementationClass;
    }

    /**
     * Return the additional interfaces introduced by this Advisor or Advice.
     *
     * @return array|string[] the introduced interfaces
     */
    public function getInterfaces()
    {
        return (array) $this->introducedInterface;
    }

    /**
     * Return the list of traits with realization of introduced interfaces
     *
     * @return array|string[] the implementations
     */
    public function getTraits()
    {
        return (array) $this->implementationClass;
    }
}
