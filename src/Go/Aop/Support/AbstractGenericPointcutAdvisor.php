<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\Advice;
use Go\Aop\PointcutAdvisor;

/**
 * Abstract generic PointcutAdvisor that allows for any Advice to be configured.
 */
abstract class AbstractGenericPointcutAdvisor implements PointcutAdvisor
{
    /**
     * @var null|Advice
     */
    private $advice = null;

    /**
     * Return whether this advice is associated with a particular instance or shared with all instances
     * of the advised class
     *
     * NB: This method was moved from AbstractPointcutAdvisor to simplify hierarchy
     *
     * @return bool Whether this advice is associated with a particular target instance
     */
    public function isPerInstance()
    {
        return true;
    }

    /**
     * Specify the advice that this advisor should apply.
     *
     * @param Advice $advice Advice to apply
     */
    public function setAdvice(Advice $advice)
    {
        $this->advice = $advice;
    }

    /**
     * Returns an advice to apply
     *
     * @return Advice|null
     */
    public function getAdvice()
    {
        return $this->advice;
    }

    /**
     * Return string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        $adviceClass = get_class($this->getAdvice());

        return get_called_class() . ": advice [{$adviceClass}]";
    }
}
