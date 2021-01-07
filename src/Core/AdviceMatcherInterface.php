<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2021, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\ParserReflection\ReflectionFileNamespace;
use ReflectionClass;

/**
 * Advice matcher returns the list of advices for the specific point of code
 */
interface AdviceMatcherInterface
{
    /**
     * Returns list of function advices for namespace
     *
     * @param Advisor[] $advisors List of advisor to match
     *
     * @return Advice[][][] List of advices for function
     */
    public function getAdvicesForFunctions(ReflectionFileNamespace $namespace, array $advisors): array;

    /**
     * Return list of advices for class
     *
     * @param Advisor[] $advisors List of advisor to match
     *
     * @return Advice[][][] List of advices for class
     */
    public function getAdvicesForClass(ReflectionClass $class, array $advisors): array;
}
