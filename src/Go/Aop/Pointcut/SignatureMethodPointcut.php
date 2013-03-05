<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use ReflectionMethod;

use Go\Aop\PointFilter;

use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class SignatureMethodPointcut extends StaticMethodMatcherPointcut
{
    /**
     * Method name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $methodName = '';

    /**
     * Modifier filter for method
     *
     * @var PointFilter
     */
    protected $modifierFilter;

    /**
     * Regular expression for matching
     *
     * @var string
     */
    protected $regexp;

    /**
     * Signature method matcher constructor
     *
     * @param string $methodName Name of the method to match or glob pattern
     * @param PointFilter $modifierFilter Method modifier filter
     */
    public function __construct($methodName, PointFilter $modifierFilter)
    {
        $this->methodName     = $methodName;
        $this->regexp         = strtr(preg_quote($this->methodName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        $this->modifierFilter = $modifierFilter;
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

        if (!$this->modifierFilter->matches($method)) {
            return false;
        }

        return ($method->name === $this->methodName) || (bool) preg_match("/^{$this->regexp}$/i", $method->name);
    }
}
