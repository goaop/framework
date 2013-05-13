<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use ReflectionClass;

/**
 * Flow pointcut is a dynamic checker that verifies stack trace to understand is it matches or not
 */
class CFlowBelowMethodPointcut extends DynamicMethodMatcherPointcut
{
    /**
     * Method name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $methodName = '';

    /**
     * Modifier mask for method
     *
     * @var string
     */
    protected $modifier;

    /**
     * Signature method matcher constructor
     *
     * @param string $methodName Name of the method to match or glob pattern
     */
    public function __construct($methodName)
    {
        $this->methodName = $methodName;
        $this->regexp     = strtr(preg_quote($this->methodName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $stackFrame) {
            $isStrongEqual = $stackFrame['function'] === $this->methodName;
            if ($isStrongEqual || (bool) preg_match("/^{$this->regexp}$/i", $stackFrame['function'])) {
                $isMatches = $this->getClassFilter()->matches(new ReflectionClass($stackFrame['class']));
                if ($isMatches) {
                    return true;
                }
            }
        }

        return false;
    }
}
