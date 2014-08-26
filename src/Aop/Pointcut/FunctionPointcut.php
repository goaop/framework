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

use ReflectionFunction;
use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use TokenReflection\ReflectionFunction as ParsedReflectionFunction;

/**
 * Signature method pointcut checks method signature (modifiers and name) to match it
 */
class FunctionPointcut implements Pointcut
{
    /**
     * @var PointFilter
     */
    protected $nsFilter;

    /**
     * Function name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $functionName = '';

    /**
     * Regular expression for matching
     *
     * @var string
     */
    protected $regexp;

    /**
     * Function matcher constructor
     *
     * @param string $functionName Name of the function to match or glob pattern
     */
    public function __construct($functionName)
    {
        $this->functionName = $functionName;
        $this->regexp       = strtr(preg_quote($this->functionName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $function Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($function)
    {
        /** @var $function ReflectionFunction|ParsedReflectionFunction */
        if (!$function instanceof ReflectionFunction && !$function instanceof ParsedReflectionFunction) {
            return false;
        }

        return ($function->name === $this->functionName) || (bool) preg_match("/^{$this->regexp}$/", $function->name);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_FUNCTION;
    }

    /**
     * Return the class filter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        return $this->nsFilter;
    }

    public function setNamespaceFilter($nsFilter)
    {
        $this->nsFilter = $nsFilter;
    }
}
