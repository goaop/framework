<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2016, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\Pointcut;
use Go\Aop\PointFilter;

/**
 * Pointcut that filters out all class method and properties which are not declared within class
 */
class ClassDeclaresPointcut implements Pointcut
{
    use PointcutClassFilterTrait;

    /**
     * {@inheritdoc}
     */
    public function matches($point, $context = null, $instance = null, array $arguments = null)
    {
        if (!$context instanceof \ReflectionClass) {
            return false;
        };

        if (!($point instanceof \ReflectionMethod) && !($point instanceof \ReflectionProperty)) {
            return false;
        }

        return $point->getDeclaringClass()->getName() === $context->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getKind()
    {
        return PointFilter::KIND_METHOD | PointFilter::KIND_PROPERTY;
    }
}
