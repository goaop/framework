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
     * Only the class is read, the aspect is never created here: advices refer to it by class and method name, the
     * aspect is resolved from the container when one of them runs for the first time.
     *
     * @param ReflectionClass<covariant Aspect> $reflectionAspect Reflection of aspect
     *
     * @return array<string,Pointcut|Advisor>
     */
    public function load(ReflectionClass $reflectionAspect): array;
}
