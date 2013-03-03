<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Go\Aop\MethodMatcher;

/**
 * Convenient abstract superclass for static method matchers, which don't care about arguments at runtime.
 */
abstract class StaticMethodMatcher implements MethodMatcher
{

    /**
     * Is this MethodMatcher dynamic or static
     *
     * Can be invoked when an AOP proxy is created, and need not be invoked again before each method invocation
     *
     * @return bool whether or not a runtime match via the 3-arg matches() method is required if static matching passed
     */
    public final function isRuntime()
    {
        return false;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_METHOD;
    }


}
