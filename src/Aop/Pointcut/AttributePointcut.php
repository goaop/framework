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
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Annotation property pointcut checks property annotation
 */
class AttributePointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * Attribute class to match
     */
    protected string $attributeClassName;

    /**
     * Kind of current filter, can be KIND_CLASS, KIND_METHOD, KIND_PROPERTY, KIND_TRAIT
     */
    protected int $filterKind;

    /**
     * Specifies name of the expected class to receive
     */
    protected string $expectedClass;

    /**
     * Static mappings of kind to expected class
     */
    protected static array $mappings = [
        self::KIND_CLASS    => ReflectionClass::class,
        self::KIND_TRAIT    => ReflectionClass::class,
        self::KIND_METHOD   => ReflectionMethod::class,
        self::KIND_PROPERTY => ReflectionProperty::class,
    ];

    /**
     * Attribute matcher constructor
     *
     * @param int $filterKind Kind of filter, e.g. KIND_CLASS
     */
    public function __construct(int $filterKind, string $attributeClassName)
    {
        if (!isset(self::$mappings[$filterKind])) {
            throw new InvalidArgumentException("Unsupported filter kind {$filterKind}");
        }
        $this->filterKind         = $filterKind;
        $this->attributeClassName = $attributeClassName;
        $this->expectedClass      = self::$mappings[$filterKind];
    }

    /**
     * @param ReflectionClass|ReflectionMethod|ReflectionProperty $point
     * {@inheritdoc}
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null): bool
    {
        $expectedClass = $this->expectedClass;
        if (!$point instanceof $expectedClass) {
            return false;
        }

        $attributes = $point->getAttributes($this->attributeClassName);

        return count($attributes) > 0;
    }

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int
    {
        return $this->filterKind;
    }
}
