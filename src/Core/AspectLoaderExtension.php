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

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use ReflectionClass;

/**
 * Extension interface that defines an API for aspect loaders
 */
interface AspectLoaderExtension
{
    /**
     * Loads definition from specific point of aspect into the container
     *
     * @param Aspect&T           $aspect           Instance of aspect
     * @param ReflectionClass<T> $reflectionAspect Reflection of aspect
     *
     * @return array<string,Pointcut>|array<string,Advisor>
     *
     * @template T of Aspect
     */
    public function load(Aspect $aspect, ReflectionClass $reflectionAspect): array;
}
