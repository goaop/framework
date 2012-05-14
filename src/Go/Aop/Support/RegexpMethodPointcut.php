<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Reflector;
use ReflectionClass;
use ReflectionMethod;

use Go\Aop\MethodMatcher;
use Go\Aop\Pointcut;
use Go\Aop\ClassFilter;
use Go\Aop\PointFilter;

/**
 * Convenient abstract superclass for static method matchers, which don't care about arguments at runtime.
 *
 * The "classFilter" property can be set to customize ClassFilter behavior.
 */
class RegexpMethodPointcut extends StaticMethodMatcherPointcut
{

    /**
     * Regular expressions to match
     *
     * @var array|string[]
     */
    private $patterns = array();

    /**
     * Regaular expressions <b>not</b> to match
     *
     * @var array
     */
    private $excludedPatterns = array();

    /**
     * Convenience method when we have only a single pattern.
     *
     * Use either this method or setPatterns(), not both.
     *
     * @param string $pattern Regular expressions to match
     */
    public function setPattern($pattern)
    {
        $this->setPatterns(array($pattern));
    }

    /**
     * Set the regular expressions defining methods to match.
     *
     * Matching will be the union of all these; if any match, the pointcut matches.
     *
     * @param array|string[] $patterns List of patterns
     */
    public function setPatterns(array $patterns)
    {
        assert('!empty($patterns); /* patterns can not be empty */');
        $this->patterns = array_map('trim', $patterns);
    }

    /**
     * Return the regular expressions for method matching.
     *
     * @return array|string[] List of patterns
     */
    public function getPatterns()
    {
        return $this->patterns;
    }

    /**
     * Convenience method when we have only a single exclusion pattern.
     *
     * @param string $pattern Pattern to exclude
     */
    public function setExcludedPattern($pattern)
    {
        $this->setExcludedPatterns(array($pattern));
    }

    /**
     * Set the regular expressions defining methods to match for exclusion.
     *
     * @param array|string[] $patterns List of patterns
     */
    public function setExcludedPatterns(array $patterns)
    {
        assert('!empty($patterns); /* patterns can not be empty */');
        $this->excludedPatterns = array_map('trim', $patterns);
    }

    /**
     * Returns the regular expressions for exclusion matching.
     *
     * @return array|string[] List of patterns
     */
    public function getExcludedPatterns()
    {
        return $this->excludedPatterns;
    }

    /**
     * Perform checking whether the given method matches.
     *
     * If this returns false or if the isRuntime() method returns false, no runtime check
     * (i.e. no. matches(ReflectionMethod, ReflectionClass, $args) call) will be made.
     *
     * @param Reflector|ReflectionMethod $method The candidate method
     * @param ReflectionClass $targetClass The target class
     * (may be null, in which case the candidate class must be taken to be the method's declaring class)
     * @param array $args Arguments to the method
     * (may be null, for statical matching)
     *
     * @return bool whether or not this method matches
     */
    public function matches(Reflector $method, ReflectionClass $targetClass = null, array $args = null)
    {
        if (!$method instanceof ReflectionMethod) {
            return false;
        }
        /** @var $methodClass ReflectionClass */
        $methodClass = $targetClass ?: $method->getDeclaringClass();
        $methodHash  = $methodClass->getName() . "->" . $method->getName();

        foreach ($this->patterns as $pattern) {
            if (preg_match("/^{$pattern}$/i", $methodHash)) {

                foreach ($this->excludedPatterns as $excludePattern) {
                    if (preg_match("/^{$excludePattern}$/i", $methodHash)) {
                        return false;
                    }
                }

                return true;
            }
        }
        return false;
    }
}
