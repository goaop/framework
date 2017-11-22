<?php
/*
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
     * Additional return type filter (if present)
     *
     * @var PointFilter|null
     */
    protected $returnTypeFilter;

    /**
     * Function matcher constructor
     *
     * @param string $functionName Name of the function to match or glob pattern
     * @param PointFilter|null $returnTypeFilter Additional return type filter
     */
    public function __construct($functionName, PointFilter $returnTypeFilter = null)
    {
        $this->functionName     = $functionName;
        $this->returnTypeFilter = $returnTypeFilter;
        $this->regexp           = strtr(preg_quote($this->functionName, '/'), [
            '\\*' => '.*?',
            '\\?' => '.'
        ]);
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $function Specific part of code, can be any Reflection class
     * @param mixed $context Related context, can be class or namespace
     * @param null|string|object $instance Invocation instance or string for static calls
     * @param null|array $arguments Dynamic arguments for method
     *
     * @return bool
     */
    public function matches($function, $context = null, $instance = null, array $arguments = null)
    {
        if (!$function instanceof ReflectionFunction) {
            return false;
        }

        if (($this->returnTypeFilter !== null) && !$this->returnTypeFilter->matches($function, $context)) {
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
