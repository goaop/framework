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

use Doctrine\Common\Annotations\Reader;
use Go\Aop\Pointcut;
use InvalidArgumentException;
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
     */
    protected string $annotationName;

    /**
     * Annotation reader
     */
    protected Reader $annotationReader;

    /**
     * Kind of current filter, can be KIND_CLASS, KIND_METHOD, KIND_PROPERTY, KIND_TRAIT
     */
    protected int $filterKind = 0;

    /**
     * Specifies name of the expected class to receive
     */
    protected string $expectedClass = '';

    /**
     * Method to call for annotation reader
     */
    protected string $annotationMethod = '';

    /**
     * Static mappings of kind to expected class and method name
     */
    protected static array $mappings = [
        self::KIND_CLASS    => [ReflectionClass::class, 'getClassAnnotation'],
        self::KIND_TRAIT    => [ReflectionClass::class, 'getClassAnnotation'],
        self::KIND_METHOD   => [ReflectionMethod::class, 'getMethodAnnotation'],
        self::KIND_PROPERTY => [ReflectionProperty::class, 'getPropertyAnnotation']
    ];

    /**
     * Annotation matcher constructor
     *
     * @param int $filterKind Kind of filter, e.g. KIND_CLASS
     */
    public function __construct(int $filterKind, Reader $annotationReader, string $annotationName)
    {
        if (!isset(self::$mappings[$filterKind])) {
            throw new InvalidArgumentException("Unsupported filter kind {$filterKind}");
        }
        $this->filterKind       = $filterKind;
        $this->annotationName   = $annotationName;
        $this->annotationReader = $annotationReader;

        [$this->expectedClass, $this->annotationMethod] = self::$mappings[$filterKind];
    }

    /**
     * {@inheritdoc}
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        $expectedClass = $this->expectedClass;
        if (!$point instanceof $expectedClass) {
            return false;
        }

        $annotation = $this->annotationReader->{$this->annotationMethod}($point, $this->annotationName);

        return (bool)$annotation;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return $this->filterKind;
    }
}
