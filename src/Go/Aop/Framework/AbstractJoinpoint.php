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
     * Name of the invocation class
     *
     * @var string
     */
    protected $className = '';

    /**
     * Initializes list of advices for current joinpoint
     *
     * @param string $className Name of the class
     * @param array $advices List of advices
     */
    protected function __construct($className, array $advices)
    {
        $this->className = $className;
        $this->advices   = static::sortAdvices($advices);
    }

    /**
     * Sorts advices by priority
     *
     * @param array|BaseAdvice[] $advices
     * @return array|BaseAdvice[] Sorted list of advices
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
