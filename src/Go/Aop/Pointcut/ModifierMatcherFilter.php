<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\PointFilter;

class ModifierMatcherFilter implements PointFilter
{

    protected $andMask = 0;

    protected $orMask = 0;

    protected $notMask = 0;

    public function __construct($initialMask = 0)
    {
        $this->andMask = $initialMask;
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
        if (!method_exists($point, 'getModifiers')) {
            return false;
        }
        $modifiers = $point->getModifiers();
        return !($this->notMask & $modifiers) &&
            (($this->andMask & $modifiers == $this->andMask) || ($this->orMask & $modifiers));
    }

    public function andMatch($value)
    {
        $this->andMask |= $value;
        return $this;
    }

    public function orMatch($value)
    {
        $this->orMask |= $value;
        return $this;
    }

    public function notMatch($value)
    {
        $this->notMask |= $value;
        return $this;
    }
}