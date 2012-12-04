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
 * Signature property pointcut checks the property signature (modifiers and name) to match it
 */
class SignaturePropertyPointcut implements Pointcut, PropertyMatcher
{

    /**
     * Filter for class
     *
     * @var null|ClassFilter
     */
    private $classFilter = null;

    /**
     * Property name to match, can contain wildcards *,?
     *
     * @var string
     */
    protected $propertyName = '';

    /**
     * Modifier mask for property
     *
     * @var string
     */
    protected $modifier;

    /**
     * Bit mask:
     *
     *  const IS_STATIC = 1;
     *  const IS_PUBLIC = 256;
     *  const IS_PROTECTED = 512;
     *  const IS_PRIVATE = 1024;
     *
     * @var integer|null
     */
    protected static $bitMask = 0x0701;

    /**
     * Signature property matcher constructor
     *
     * @param string $propertyName Name of the property to match or glob pattern
     * @param integer $modifier Method modifier (mask of reflection constant modifiers)
     */
    public function __construct($propertyName, $modifier)
    {
        $this->propertyName = $propertyName;
        $this->regexp     = strtr(preg_quote($this->propertyName, '/'), array(
            '\\*' => '.*?',
            '\\?' => '.'
        ));
        $this->modifier   = $modifier;
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

        $modifiers = $property->getModifiers();
        if (!($modifiers & $this->modifier) || ((self::$bitMask - $this->modifier) & $modifiers)) {
            return false;
        }

        return ($property->name === $this->propertyName) || (bool) preg_match("/^{$this->regexp}$/i", $property->name);
    }
}
