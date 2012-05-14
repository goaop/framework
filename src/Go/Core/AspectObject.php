<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Go\Aop\Aspect;

/**
 * @package go
 * @subpackage core
 */
abstract class AspectObject extends Object
{
    protected function init()
    {
        parent::init();
        $joinPointAdvices = Aspect::getJoinPoints($this);
        foreach($joinPointAdvices as $pointName => $joinPoint) {
            $this->$pointName = $joinPoint;
        }
    }

    abstract public function getClosures();

    function __call($name, $arguments)
    {
        $joinPoint = $this->$name;
        return $joinPoint(reset($arguments));
    }
}
