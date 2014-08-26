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
     * Is this MethodMatcher dynamic or static
     *
     * Can be invoked when an AOP proxy is created, and need not be invoked again before each method invocation
     *
     * @return bool whether or not a runtime match via the 3-arg matches() method is required if static matching passed
     */
    final public function isRuntime()
    {
        return true;
    }

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
