<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\MethodMatcher;
use ReflectionMethod;
use Go\Aop\PointFilter;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class SignatureMethodPointcut extends StaticMethodMatcherPointcut implements MethodMatcher
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
            '\\?' => '.',
            '\\|' => '|'
        ));
        $this->modifierFilter = $modifierFilter;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $method Specific part of code, can be any Reflection class
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($method, $instance = null, array $arguments = null)
    {
        /** @var $method ReflectionMethod|ParsedReflectionMethod */
        if (!$method instanceof ReflectionMethod && !$method instanceof ParsedReflectionMethod) {
            return false;
        }

        if (!$this->modifierFilter->matches($method)) {
            return false;
        }

        return ($method->name === $this->methodName) || (bool) preg_match("/^(?:{$this->regexp})$/", $method->name);
    }
}
