<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\aop\framework;

use org\aopalliance\intercept\Joinpoint;
use go\aop\framework\BaseAdvice;
use go\aop\AdviceAfter;
use go\aop\AdviceBefore;
use go\aop\AdviceAround;

/**
 *  Abstract joinpoint for framework
 *
 * Join points are points in the execution of the system, such as method calls,
 * where behavior supplied by aspects is combined. A join point is a point in
 * the execution of the program, which is used to define the dynamic structure
 * of a crosscutting concern.
 *
 * @link http://en.wikipedia.org/wiki/Aspect-oriented_software_development#Join_point_model
 * @package go
 */
abstract class AbstractJoinpoint implements Joinpoint
{
    /** @var array|\go\aop\Advice[] */
    protected $advices = array();

    /**
     * Initializes list of advices for current joinpoint
     *
     * @param array|\go\aop\Advice[] $advices
     * @return \go\aop\framework\AbstractJoinpoint
     */
    protected function __construct(array $advices)
    {
        $this->advices = static::sortAdvices($advices);
    }

    /**
     * Sorts advices by priority
     *
     * @param array|\go\aop\framework\BaseAdvice[] $advices
     * @return array|\go\aop\framework\BaseAdvice[] Sorted list of advices
     */
    static protected function sortAdvices(array $advices)
    {
        $sortedAdvices = $advices;
        usort($sortedAdvices, function(BaseAdvice $first, BaseAdvice $second) {
            if ($first instanceof AdviceBefore && !($second instanceof AdviceBefore)) {
                return -1;
            } elseif ($first instanceof AdviceAround && !($second instanceof AdviceAround)) {
                return 1;
            } elseif ($first instanceof AdviceAfter && !($second instanceof AdviceAfter)) {
                return $second instanceof AdviceBefore ? 1 : -1;
            } else {
                return $first->getAdviceOrder() - $second->getAdviceOrder();
            }
        });
        return $sortedAdvices;
    }
}
