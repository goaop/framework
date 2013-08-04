<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;
use Go\Aop\Support\TruePointFilter;
use ReflectionProperty;

use Go\Instrument\RawAnnotationReader;

use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

/**
 * Annotation property pointcut checks property annotation
 */
class AnnotationPropertyPointcut implements Pointcut
{

    /**
     * Filter for class
     *
     * @var null|PointFilter
     */
    private $classFilter = null;

    /**
     * Annotation class to match
     *
     * @var string
     */
    protected $annotationName = '';

    /**
     * Annotation reader
     *
     * @var null|RawAnnotationReader
     */
    protected $annotationReader = null;

    /**
     * Annotation property matcher constructor
     *
     * @param RawAnnotationReader $reader Annotation reader
     * @param string $annotationName Name of the annotation class to match
     */
    public function __construct(RawAnnotationReader $reader, $annotationName)
    {
        $this->annotationName  = $annotationName;
        $this->annotationReader = $reader;
    }

    /**
     * Performs matching of point of code
     *
     * @param $property ReflectionProperty|ParsedReflectionProperty Specific part of code, can be any Reflection class
     *
     * @return bool
     */
    public function matches($property)
    {
        if (!$property instanceof ReflectionProperty && !$property instanceof ParsedReflectionProperty) {
            return false;
        }
        if ($property instanceof ParsedReflectionProperty) {
            $imports = $property->getNamespaceAliases();
            $this->annotationReader->setImports($imports);
        }
        $annotation = $this->annotationReader->getPropertyAnnotation($property, $this->annotationName);
        return (bool) $annotation;
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
}
