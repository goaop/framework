<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Pointcut;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Annotation property pointcut checks property annotation
 */
class AnnotationPointcut implements Pointcut
{
    use PointcutClassFilterTrait;
    /**
     * Annotation class to match
     *
     * @var string
     */
    protected $annotationName = '';

    /**
     * Annotation reader
     *
     * @var null|Reader
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
    protected static $mappings = [
        self::KIND_CLASS    => [ReflectionClass::class, 'getClassAnnotation'],
        self::KIND_TRAIT    => [ReflectionClass::class, 'getClassAnnotation'],
        self::KIND_METHOD   => [ReflectionMethod::class, 'getMethodAnnotation'],
        self::KIND_PROPERTY => [ReflectionProperty::class, 'getPropertyAnnotation']
    ];

    /**
     * Annotation matcher constructor
     *
     * @param integer $filterKind Kind of filter, e.g. KIND_CLASS
     * @param Reader $reader Reader of annotations
     * @param string $annotationName Annotation class name to match
     */
    public function __construct(int $filterKind, Reader $reader, string $annotationName)
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
     * {@inheritdoc}
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null) : bool
    {
        $expectedClass = $this->expectedClass;
        if (!$point instanceof $expectedClass) {
            return false;
        }

        $annotation = $this->annotationReader->{$this->annotationMethod}($point, $this->annotationName);

        return (bool) $annotation;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind() : int
    {
        return $this->filterKind;
    }
}
