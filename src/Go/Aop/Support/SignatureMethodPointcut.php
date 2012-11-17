<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionMethod;

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
     * Modifier mask for method
     *
     * @var string
     */
    protected $modifier;

    /**
     * Signature method matcher constructor
     *
     * @param string $methodName Name of the method to match or glob pattern
     * @param integer $modifier Method modifier (mask of reflection constant modifiers)
     */
    public function __construct($methodName, $modifier)
    {
        $this->methodName = $methodName;
        $this->regexp     = strtr(preg_quote($this->methodName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        $this->modifier   = $modifier;
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

        if (!($method->getModifiers() & $this->modifier)) {
            return false;
        }

        return ($method->name === $this->methodName) || (bool) preg_match("/^{$this->regexp}$/i", $this->methodName);
    }
}
