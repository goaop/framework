<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use ReflectionFunction;

/**
 * Function pointcut checks function signature (namespace and name) to match it
 */
class FunctionPointcut implements Pointcut
{
    protected ?PointFilter $nsFilter = null;

    /**
     * Function name to match, can contain wildcards *,?
     */
    protected string $functionName = '';

    /**
     * Regular expression for matching
     */
    protected string $regexp;

    /**
     * Additional return type filter (if present)
     */
    protected ?PointFilter $returnTypeFilter = null;

    /**
     * Function matcher constructor
     */
    public function __construct(string $functionName, PointFilter $returnTypeFilter = null)
    {
        $this->functionName     = $functionName;
        $this->returnTypeFilter = $returnTypeFilter;
        $this->regexp           = strtr(
            preg_quote($this->functionName, '/'),
            [
                '\\*' => '.*?',
                '\\?' => '.'
            ]
        );
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed              $function  Specific part of code, can be any Reflection class
     * @param mixed              $context   Related context, can be class or namespace
     * @param null|string|object $instance  Invocation instance or string for static calls
     * @param null|array         $arguments Dynamic arguments for method
     */
    public function matches($function, $context = null, $instance = null, array $arguments = null): bool
    {
        if (!$function instanceof ReflectionFunction) {
            return false;
        }

        if (($this->returnTypeFilter !== null) && !$this->returnTypeFilter->matches($function, $context)) {
            return false;
        }

        return ($function->name === $this->functionName) || (bool)preg_match("/^{$this->regexp}$/", $function->name);
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return self::KIND_FUNCTION;
    }

    /**
     * Return the class filter for this pointcut.
     */
    public function getClassFilter(): PointFilter
    {
        return $this->nsFilter;
    }

    /**
     * Configures the namespace filter, used as pre-filter for functions
     */
    public function setNamespaceFilter(PointFilter $nsFilter): void
    {
        $this->nsFilter = $nsFilter;
    }
}
