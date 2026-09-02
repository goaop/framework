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

namespace Go\Aop\Framework;

use Go\Aop\Advice;
use Go\Aop\AspectException;
use Go\Aop\IntroductionInfo;
use Go\Aop\Intercept\Interceptor;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\OrderedAdvice;

/**
 *  Abstract joinpoint for framework
 *
 * Join points are points in the execution of the system, such as method calls,
 * where behavior supplied by aspects is combined. A join point is a point in
 * the execution of the program, which is used to define the dynamic structure
 * of a crosscutting concern.
 *
 * @link http://en.wikipedia.org/wiki/Aspect-oriented_software_development#Join_point_model
 */
abstract class AbstractJoinpoint implements Joinpoint
{
    /**
     * Current advice index
     */
    protected int $current = 0;

    /**
     * Recursion level for invocation
     */
    protected int $level = 0;

    /**
     * Initializes list of advices for current joinpoint
     *
     * @param array<Interceptor> $advices List of advices (interceptors)
     */
    public function __construct(protected readonly array $advices = []) {}

    /**
     * Sorts advices by priority
     *
     * @param array<mixed> $advices
     *
     * @return array<mixed> Sorted list of advices
     */
    public static function sortAdvices(array $advices): array
    {
        $sortedAdvices = $advices;
        uasort(
            $sortedAdvices,
            function (mixed $first, mixed $second): int {
                if ($first instanceof Advice && $second instanceof Advice) {
                    $priority = $first->getType()->compareTo($second->getType());
                    if ($priority !== 0) {
                        return $priority;
                    }
                }

                return $first instanceof OrderedAdvice && $second instanceof OrderedAdvice
                    ? $first->getAdviceOrder() - $second->getAdviceOrder()
                    : 0;
            }
        );

        return $sortedAdvices;
    }

    /**
     * Replace concrete advices with generated-code descriptors or introduction ids.
     *
     * @param array<string, array<string, array<string, mixed>>> $advices List of advices
     *
     * @return array<string, array<string, list<string|GeneratedInterceptor>>> Sorted advices/interceptors
     */
    public static function flatAndSortAdvices(array $advices): array
    {
        $flattenAdvices = [];
        foreach ($advices as $type => $typedAdvices) {
            foreach ($typedAdvices as $name => $concreteAdvices) {
                foreach (self::sortAdvices($concreteAdvices) as $advisorId => $advice) {
                    if ($advice instanceof IntroductionInfo) {
                        $flattenAdvices[$type][$name][] = (string) $advisorId;

                        continue;
                    }
                    if (!$advice instanceof Advice) {
                        throw new AspectException(
                            "Advisor {$advisorId} provides " . get_debug_type($advice) . ' instead of advice instance'
                        );
                    }
                    $flattenAdvices[$type][$name][] = GeneratedInterceptor::fromAdvice((string) $advisorId, $advice);
                }
            }
        }

        return $flattenAdvices;
    }
}
