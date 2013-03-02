<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use ReflectionMethod;

use Go\Aop\Support\InheritanceClassFilter;
use Go\Aop\Support\SimpleClassFilter;
use Go\Aop\Support\StaticMethodMatcherPointcut;

use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * Within method pointcut matches all methods in specific class or namespace
 */
class WithinMethodPointcut extends StaticMethodMatcherPointcut
{

    /**
     * Within method matcher constructor
     *
     * @param string $className Name of the class or namespace pattern to match
     * @param integer $modifier Method modifier (mask of reflection constant modifiers)
     */
    public function __construct($className, $withChildren = false)
    {
        if (!$withChildren) {
            $this->setClassFilter(new SimpleClassFilter($className));
        } elseif (strpos($className, '*') === false) {
            $this->setClassFilter(new InheritanceClassFilter($className));
        } else {
            throw new \InvalidArgumentException("Can not use children selector with class mask");
        }
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $method Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($method)
    {
        /** @var $method ReflectionMethod|ParsedReflectionMethod */
        if (!$method instanceof ReflectionMethod && !$method instanceof ParsedReflectionMethod) {
            return false;
        }

        return true;
    }
}
