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
                switch(count($args)) {
                    case 0:
                        return parent::$method();
                    case 1:
                        return parent::$method($args[0]);
                    case 2:
                        return parent::$method($args[0], $args[1]);
                    case 3:
                        return parent::$method($args[0], $args[1], $args[2]);
                    case 4:
                        return parent::$method($args[0], $args[1], $args[2], $args[3]);
                    case 5:
                        return parent::$method($args[0], $args[1], $args[2], $args[3], $args[4]);
                    default:
                        return forward_static_call_array(array($parentClass, $method), $args);
                }
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
            switch(count($args)) {
                case 0:
                    return parent::$method();
                case 1:
                    return parent::$method($args[0]);
                case 2:
                    return parent::$method($args[0], $args[1]);
                case 3:
                    return parent::$method($args[0], $args[1], $args[2]);
                case 4:
                    return parent::$method($args[0], $args[1], $args[2], $args[3]);
                case 5:
                    return parent::$method($args[0], $args[1], $args[2], $args[3], $args[4]);
                default:
                    return call_user_func_array(array('parent', $method), $args);
            }
        };
    }
}
