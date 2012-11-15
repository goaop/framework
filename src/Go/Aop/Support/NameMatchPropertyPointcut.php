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
use ReflectionProperty;

use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

use Go\Aop\PropertyMatcher;
use Go\Aop\Pointcut;
use Go\Aop\ClassFilter;
use Go\Aop\PointFilter;
use Go\Aop\TrueClassFilter;

/**
 * Pointcut class for simple method name matches, as alternative to regexp patterns.
 *
 * Does not handle overloaded methods: all methods *with a given name will be eligible.
 */
class NameMatchPropertyPointcut implements Pointcut, PropertyMatcher
{

    /**
     * List of mapped names
     *
     * @var array|string[]
     */
    private $mappedNames = array();

    /**
     * Filter for class
     *
     * @var null|ClassFilter
     */
    private $classFilter = null;

    /**
     * Return the PointFilter for this pointcut.
     *
     * @return PointFilter
     */
    public function getPointFilter()
    {
        return $this;
    }

    /**
     * Convenience method when we have only a single property name to match.
     *
     * Use either this method or setMappedNames, not both.
     *
     * @param string $mappedName Name of the additional property that will match
     */
    public function setMappedName($mappedName)
    {
        $this->setMappedNames(array($mappedName));
    }

    /**
     * Set the property names defining properties to match.
     *
     * Matching will be the union of all these; if any match, the pointcut matches.
     *
     * @param array|string[] $mappedNames List of properties that will match
     */
    public function setMappedNames(array $mappedNames)
    {
        assert('!empty($mappedNames); /* mappedNames can not be empty */');
        $this->mappedNames = array_map('trim', $mappedNames);
    }

    /**
     * Add another eligible property name, in addition to those already named.
     *
     * Like the set methods, this method is for use when configuring proxies, before a proxy is used.
     *
     * @param string $name Name of the additional property that will match
     *
     * @return NameMatchPropertyPointcut
     */
    public function addPropertyName($name)
    {
        assert('!empty($name); /* name can not be empty */');
        $this->mappedNames[] = trim($name);
        return $this;
    }

    /**
     * Set the ClassFilter to use for this pointcut.
     *
     * @param ClassFilter $classFilter
     */
    public function setClassFilter(ClassFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return the ClassFilter for this pointcut.
     *
     * @return ClassFilter
     */
    public function getClassFilter()
    {
        if (!$this->classFilter) {
            $this->classFilter = TrueClassFilter::getInstance();
        }
        return $this->classFilter;
    }

    /**
     * {@inheritdoc}
     */
    public function matches($property)
    {
        /** @var $property ReflectionProperty|ParsedReflectionProperty */
        if (!$property instanceof ReflectionProperty && !$property instanceof ParsedReflectionProperty) {
            return false;
        }

        $propertyName = $property->name;
        foreach ($this->mappedNames as $mappedName) {
            if ($mappedName === $propertyName || $this->isMatch($propertyName, $mappedName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return if the given property name matches the mapped name.
     *
     * The default implementation checks for "xxx*", "*xxx" and "*xxx*" matches, as well as direct equality.
     * Can be overridden in subclasses.
     *
     * @param string $propertyName The property name of the class
     * @param string $mappedName The name in the descriptor
     *
     * @return bool If the names match
     */
    protected function isMatch($propertyName, $mappedName)
    {
        $regexp = strtr(preg_quote($mappedName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        return (bool) preg_match("/^{$regexp}$/i", $propertyName);
    }
}
