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

use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

use Go\Aop\MethodMatcher;
use Go\Aop\Pointcut;
use Go\Aop\ClassFilter;
use Go\Aop\PointFilter;

/**
 * Pointcut class for simple method name matches, as alternative to regexp patterns.
 *
 * Does not handle overloaded methods: all methods *with a given name will be eligible.
 */
class NameMatchMethodPointcut extends StaticMethodMatcherPointcut
{

    /**
     * List of mapped names
     *
     * @var array|string[]
     */
    private $mappedNames = array();

    /**
     * Convenience method when we have only a single method name to match.
     *
     * Use either this method or setMappedNames, not both.
     *
     * @param string $mappedName Name of the additional method that will match
     */
    public function setMappedName($mappedName)
    {
        $this->setMappedNames(array($mappedName));
    }

    /**
     * Set the method names defining methods to match.
     *
     * Matching will be the union of all these; if any match, the pointcut matches.
     *
     * @param array|string[] $mappedNames List of methods that will match
     */
    public function setMappedNames(array $mappedNames)
    {
        assert('!empty($mappedNames); /* mappedNames can not be empty */');
        $this->mappedNames = array_map('trim', $mappedNames);
    }

    /**
     * Add another eligible method name, in addition to those already named.
     *
     * Like the set methods, this method is for use when configuring proxies, before a proxy is used.
     *
     * @param string $name Name of the additional method that will match
     *
     * @return NameMatchMethodPointcut
     */
    public function addMethodName($name)
    {
        assert('!empty($name); /* name can not be empty */');
        $this->mappedNames[] = trim($name);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($method, $targetClass = null, array $args = null)
    {
        /** @var $method ReflectionMethod|ParsedReflectionMethod */
        if (!$method instanceof ReflectionMethod && !$method instanceof ParsedReflectionMethod) {
            return false;
        }

        $methodName = $method->name;
        foreach ($this->mappedNames as $mappedName) {
            if ($mappedName === $methodName || $this->isMatch($methodName, $mappedName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return if the given method name matches the mapped name.
     *
     * The default implementation checks for "xxx*", "*xxx" and "*xxx*" matches, as well as direct equality.
     * Can be overridden in subclasses.
     *
     * @param string $methodName The method name of the class
     * @param string $mappedName The name in the descriptor
     *
     * @return bool If the names match
     */
    protected function isMatch($methodName, $mappedName)
    {
        $regexp = strtr(preg_quote($mappedName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        return (bool) preg_match("/^{$regexp}$/i", $methodName);
    }
}
