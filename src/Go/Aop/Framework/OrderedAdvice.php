<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Framework;

use Go\Aop\Advice;

/**
 * Ordered advice can have a custom order to implement sorting
 *
 * @author Lissachenko Alexander
 */
interface OrderedAdvice extends Advice
{
    /**
     * Returns the advice order
     *
     * @return int
     */
    public function getAdviceOrder();
}
