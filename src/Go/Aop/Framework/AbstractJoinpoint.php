<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Advice;
use Go\Aop\AdviceAfter;
use Go\Aop\AdviceBefore;
use Go\Aop\AdviceAround;
use Go\Aop\Intercept\Joinpoint;


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
    /**
     * List of advices
     *
     * @var array|Advice[]
     */
    protected $advices = array();

    /**
     * Current advice index
     *
     * @var int
     */
    protected $current = 0;

    /**
     * Stack frames to work with recursive calls or with cross-calls inside object
     *
     * @var array
     */
    protected $stackFrames = array();

    /**
     * Recursion level for invocation
     *
     * @var int
     */
    protected $level = 0;

    /**
     * Initializes list of advices for current joinpoint
     *
     * @param array $advices List of advices
     */
    public function __construct(array $advices)
    {
        $this->advices = $advices;
    }

    /**
     * Sorts advices by priority
     *
     * @param array|Advice[] $advices
     * @return array|Advice[] Sorted list of advices
     */
    static public function sortAdvices(array $advices)
    {
        $sortedAdvices = $advices;
        usort($sortedAdvices, function(Advice $first, Advice $second) {
            switch (true) {
                case $first instanceof AdviceBefore && !($second instanceof AdviceBefore):
                    return -1;

                case $first instanceof AdviceAround && !($second instanceof AdviceAround):
                    return 1;

                case $first instanceof AdviceAfter && !($second instanceof AdviceAfter):
                    return $second instanceof AdviceBefore ? 1 : -1;

                case ($first instanceof OrderedAdvice && $second instanceof OrderedAdvice):
                    return $first->getAdviceOrder() - $second->getAdviceOrder();

                default:
                    return 0;
            }
        });
        return $sortedAdvices;
    }
}
