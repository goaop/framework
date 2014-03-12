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

use Go\Aop\PointFilter;

/**
 * Canonical PointFilter instance that matches all points.
 */
class TruePointFilter implements PointFilter
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
     * @return self
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
     * Performs matching of point of code
     *
     * @param mixed $point Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($point)
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
        return self::KIND_ALL;
    }
}
