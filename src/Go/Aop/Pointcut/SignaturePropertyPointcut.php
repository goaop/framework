<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use ReflectionProperty;

use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\TruePointFilter;

/**
 * Signature property pointcut checks the property signature (modifiers and name) to match it
 */
class SignaturePropertyPointcut implements Pointcut, PointFilter
{

    /**
     * Filter for class
     *
     * @var null|PointFilter
     */
    private $classFilter = null;

    /**
     * Modifier filter for method
     *
     * @var PointFilter
     */
    protected $modifierFilter;

    /**
     * Property name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $propertyName = '';

    /**
     * Signature property matcher constructor
     *
     * @param string $propertyName Name of the property to match or glob pattern
     * @param PointFilter $modifierFilter Property modifier filter
     */
    public function __construct($propertyName, PointFilter $modifierFilter)
    {
        $this->propertyName   = $propertyName;
        $this->regexp         = strtr(preg_quote($this->propertyName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        $this->modifierFilter = $modifierFilter;
    }

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
     * Set the ClassFilter to use for this pointcut.
     *
     * @param PointFilter $classFilter
     */
    public function setClassFilter(PointFilter $classFilter)
    {
        $this->classFilter = $classFilter;
    }

    /**
     * Return the ClassFilter for this pointcut.
     *
     * @return PointFilter
     */
    public function getClassFilter()
    {
        if (!$this->classFilter) {
            $this->classFilter = TruePointFilter::getInstance();
        }
        return $this->classFilter;
    }

    /**
     * Performs matching of point of code
     *
     * @param mixed $property
     *
     * @return bool
     */
    public function matches($property)
    {
        /** @var $property ReflectionProperty|ParsedReflectionProperty */
        if (!$property instanceof ReflectionProperty && !$property instanceof ParsedReflectionProperty) {
            return false;
        }

        if (!$this->modifierFilter->matches($property)) {
            return false;
        }

        return ($property->name === $this->propertyName) || (bool) preg_match("/^{$this->regexp}$/i", $property->name);
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return self::KIND_PROPERTY;
    }
}
