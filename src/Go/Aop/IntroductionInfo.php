<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

/**
 * Interface supplying the information necessary to describe an introduction of trait.
 *
 * If an Advice implements this, it may be used as an introduction without an IntroductionAdvisor.
 * In this case, the advice is self-describing, providing not only the necessary behavior,
 * but describing the interfaces it introduces.
 */
interface IntroductionInfo extends Advice
{

    /**
     * Return the additional interfaces introduced by this Advisor or Advice.
     *
     * @return array|string[] the introduced interfaces
     */
    public function getInterfaces();

    /**
     * Return the list of traits with realization of introduced interfaces
     *
     * @return array|string[] the implementations
     */
    public function getTraits();
}
