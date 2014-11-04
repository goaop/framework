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

use Go\Aop\MethodMatcher;

/**
 * Convenient abstract superclass for dynamic method matchers, which do care about arguments at runtime.
 */
abstract class DynamicMethodMatcher implements MethodMatcher
{
    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_METHOD | self::KIND_DYNAMIC;
    }
}
