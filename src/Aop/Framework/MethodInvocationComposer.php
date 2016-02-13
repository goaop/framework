<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

/**
 * Composer for method invocations
 *
 * This technique allows to build a class for maximum performance of method invocation
 */
class MethodInvocationComposer
{
    /**
     * Composes a class with specific features and returns its name
     *
     * @param bool $isStatic Static or dynamic method
     * @param bool $useSplatOperator Enables usage of optimized invocation with splat operator
     * @param bool $useVariadics Enables usage of optimized invocation with variadic args
     *
     * @return string Name of composed class
     */
    public static function compose($isStatic, $useSplatOperator, $useVariadics)
    {
        $className = __NAMESPACE__ . '\\';
        $className .= $isStatic ? 'Static' : 'Dynamic';

        $className .= 'Closure';
        if ($useSplatOperator && !$isStatic) {
            $className .= 'Splat';
        }

        if ($useVariadics) {
            $className .= 'Variadic';
        }

        $className .= 'MethodInvocation';

        return $className;
    }
}
