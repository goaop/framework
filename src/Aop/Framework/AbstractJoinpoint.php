<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
 */
abstract class AbstractJoinpoint implements Joinpoint
{
    /**
     * List of advices
     *
     * @var array|Advice[]
     */
    protected $advices = [];

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
    protected $stackFrames = [];

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
    public static function sortAdvices(array $advices)
    {
        $sortedAdvices = $advices;
        uasort($sortedAdvices, function(Advice $first, Advice $second) {
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
