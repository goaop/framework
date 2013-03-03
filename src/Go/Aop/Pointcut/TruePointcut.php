<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\PointFilter;
use Go\Aop\Pointcut;
use Go\Aop\Support\TruePointFilter;

/**
 * Canonical Pointcut instance that always matches.
 *
 * @package go
 * @subpackage aop
 */
class TruePointcut implements Pointcut
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
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        return TruePointFilter::getInstance();
    }

    /**
     * Return the PointFilter for this pointcut.
     *
     * This can be method filter, property filter.
     *
     * @return PointFilter
     */
    public function getPointFilter()
    {
        return TruePointFilter::getInstance();
    }
}
