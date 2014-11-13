<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\PointFilter;
use Go\Instrument\RawAnnotationReader;
use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

/**
 * Annotation filter that matches class/property/method by annotation class name
 */
class AnnotationFilter implements PointFilter
{
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
     * Kind of current filter, can be KIND_CLASS, KIND_METHOD, KIND_PROPERTY, KIND_TRAIT
     *
     * @var int
     */
    protected $filterKind = 0;

    /**
     * Specifies name of the expected class to receive
     *
     * @var string
     */
    protected $expectedClass = '';

    /**
     * Method to call for annotation reader
     *
     * @var string
     */
    protected $annotationMethod = '';

    /**
     * Static mappings of kind to expected class and method name
     *
     * @var array
     */
    protected static $mappings = array(
        self::KIND_CLASS => array(
            'TokenReflection\ReflectionClass',
            'getClassAnnotation'
        ),
        self::KIND_TRAIT => array(
            'TokenReflection\ReflectionClass',
            'getClassAnnotation'
        ),
        self::KIND_METHOD => array(
            'TokenReflection\ReflectionMethod',
            'getMethodAnnotation'
        ),
        self::KIND_PROPERTY => array(
            'TokenReflection\ReflectionProperty',
            'getPropertyAnnotation'
        )
    );

    /**
     * Annotation matcher constructor
     *
     * @param integer $filterKind Kind of filter, e.g. KIND_CLASS
     * @param RawAnnotationReader $reader Reader of annotations
     * @param string $annotationName Annotation class name to match
     */
    public function __construct($filterKind, RawAnnotationReader $reader, $annotationName)
    {
        if (!isset(self::$mappings[$filterKind])) {
            throw new \InvalidArgumentException("Unsupported filter kind {$filterKind}");
        }
        $this->filterKind       = $filterKind;
        $this->annotationName   = $annotationName;
        $this->annotationReader = $reader;

        list($this->expectedClass, $this->annotationMethod) = self::$mappings[$filterKind];
    }

    /**
     * @param ParsedReflectionClass|ParsedReflectionMethod|ParsedReflectionProperty $point
     * {@inheritdoc}
     */
    public function matches($point)
    {
        $expectedClass = $this->expectedClass;
        if (!$point instanceof $expectedClass) {
            return false;
        }

        $aliases = $point->getNamespaceAliases();
        $this->annotationReader->setImports($aliases);

        $annotation = $this->annotationReader->{$this->annotationMethod}($point, $this->annotationName);

        return (bool) $annotation;
    }

    /**
     * Returns the kind of point filter
     *
     * @return integer
     */
    public function getKind()
    {
        return $this->filterKind;
    }
}
