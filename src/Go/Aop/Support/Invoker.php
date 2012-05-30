<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

/**
 * Invoker class is used to prepare closure for method invocation
 *
 * NB: This class is used only with PHP>=5.4.0 to build closure invocation.
 *
 * @author Lissachenko Alexander <lisachenko.it@gmail.com>
 * @package go
 */
class Invoker
{

    /**
     * Restrict direct creation
     */
    final private function __construct()
    {
    }


    /**
     * Returns static method invoker
     *
     * @return closure
     */
    public static function getStatic()
    {
        static $invoker = null;
        if (!$invoker) {
            $invoker = function ($parentClass, $method, array $args) {
                return forward_static_call_array(array($parentClass, $method), $args);
            };
        }
        return $invoker;
    }

    /**
     * Returns dynamic parent invoker
     *
     * @return Closure
     */
    public static function getDynamicParent()
    {
        static $invoker = null;
        if (!$invoker) {
            $instance = new self();
            $invoker = $instance->getDynamicInvoker();
        }
        return $invoker;
    }

    /**
     * Private method that will use object instance to build dynamic parent invoker
     *
     * @return Closure
     */
    private function getDynamicInvoker()
    {
        return function ($parentClass, $method, array $args) {
            return call_user_func_array(array('parent', $method), $args);
        };
    }
}
