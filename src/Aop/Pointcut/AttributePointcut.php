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
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Attribute property pointcut checks joinpoint attributes
 *
 * @see https://www.php.net/manual/en/reflectionfunctionabstract.getattributes.php
 * @see https://www.php.net/manual/en/reflectionclass.getattributes.php
 * @see https://www.php.net/manual/en/reflectionproperty.getattributes.php
 *
 */
final readonly class AttributePointcut implements Pointcut
{
    /**
     * Attribute matcher constructor
     *
     * @param int $pointcutKind Kind of current filter, can be KIND_CLASS, KIND_METHOD, KIND_PROPERTY, KIND_TRAIT
     * @param (string&class-string) $attributeClassName Attribute class to match
     */
    public function __construct(
        private int    $pointcutKind,
        private string $attributeClassName,
        private bool   $useContextForMatching = false,
    ) {}

    final public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction $reflector = null,
        object|string                                          $instanceOrScope = null,
        array                                                  $arguments = null
    ): bool {
        // If we don't use context for matching and we do static check, then always match
        if (!$this->useContextForMatching && !isset($reflector)) {
            return true;
        }

        // Otherwise we select either context for matching (eg for @within) or reflector (eg for @execution)
        if ($this->useContextForMatching) {
            $instanceToCheck = $context;
        } else {
            $instanceToCheck = $reflector;
        }

        if (!isset($instanceToCheck) || !method_exists($instanceToCheck, 'getAttributes')) {
            return false;
        }

        // Final static matching by checking attributes for given reflector
        return count($instanceToCheck->getAttributes($this->attributeClassName)) > 0;
    }

    public function getKind(): int
    {
        return $this->pointcutKind;
    }
}
