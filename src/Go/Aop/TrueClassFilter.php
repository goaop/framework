<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

use Reflector;
use ReflectionClass;

use Go\Aop\ClassFilter;

/**
 * Canonical ClassFilter instance that matches all classes.
 */
class TrueClassFilter implements ClassFilter
{

    /**
     * Private class constructor
     */
    private function __construct()
    {

    }

    public static function getInstance()
    {
        static $instance = null;
        if (!$instance) {
            $instance = new self();
        }
        return $instance;
    }

    /**
     * Performs matching of class
     *
     * @param Reflector|ReflectionClass $class Class instance
     *
     * @return bool
     */
    public function matches(Reflector $class)
    {
        // Is check for a ReflectionClass class is needed here?
        return true;
    }
}
