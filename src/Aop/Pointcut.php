<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop;

use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Pointcut is responsible for matching any reflection items statically.
 *
 * Matcher uses smart technique of matching elements, consisting of several stages described below.
 *
 * First stage of matching involves context only (just one argument). This pre-stage is used to optimize
 * filtering on matcher side to avoid nested loops of checks. For example, if we have a method pointcut, but
 * it doesn't match first with class, then we don't need to scan all methods at all and can exit earlier.
 *
 * Here is a mapping of context for different static joinpoints:
 *  - For any traits or classes, context will be `ReflectionClass` corresponding to the given class or trait.
 *  - For any functions, context will be `ReflectionFileNamespace` where internal function is analyzed.
 *  - For any methods or properties, context will be `ReflectionClass` which is currently analysed (even for inherited items)
 *
 * Second stage of matching uses exactly two arguments (context and reflector). Filter then fully checks
 * static information from reflection to make a decision about matching of given point.
 *
 * At this stage we can verify names, attributes, signature, parameters, types, etc.
 *
 * Evaluation ends here statically, and generated code will not contain any runtime checks
 * for given point filter, allowing for better performance.
 */
interface Pointcut
{
    public const KIND_METHOD       = 1;
    public const KIND_PROPERTY     = 2;
    public const KIND_CLASS        = 4;
    public const KIND_TRAIT        = 8;
    public const KIND_FUNCTION     = 16;
    public const KIND_INIT         = 32;
    public const KIND_STATIC_INIT  = 64;
    public const KIND_ALL          = 127;
    public const KIND_INTRODUCTION = 512;

    /**
     * Returns the kind of point filter
     */
    public function getKind(): int;

    /**
     * Performs matching of point of code, returns true if point matches
     *
     * @param ReflectionClass<T>|ReflectionFileNamespace $context                    Related context, can be class or file namespace
     * @param ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector Specific part of code, can be any Reflection class
     *
     * @template T of object
     */
    public function matches(
        ReflectionClass|ReflectionFileNamespace                $context,
        ReflectionMethod|ReflectionProperty|ReflectionFunction|null $reflector = null
    ): bool;
}