<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop;

use ReflectionClass;
use TokenReflection\ReflectionClass as ParsedReflectionClass;

/**
 * Canonical ClassFilter instance that matches all classes.
 */
class TrueClassFilter implements PointFilter
{

    /**
     * Private class constructor
     */
    private function __construct()
    {

    }

    /**
     * Singleton pattern
     *
     * @return TrueClassFilter
     */
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
     * @param ReflectionClass|ParsedReflectionClass $class Class instance
     *
     * @return bool
     */
    public function matches($class)
    {
        // Is check for a ReflectionClass class is needed here?
        return true;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_CLASS;
    }
}
