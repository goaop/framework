<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Framework;

use Closure;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Accessor for aspect instances from generated proxy code.
 */
final class The
{
    /**
     * @template T of Aspect
     * @param class-string<T> $aspectClass
     * @return T
     */
    public static function aspect(string $aspectClass): Aspect
    {
        return self::getContainer()->getService($aspectClass);
    }

    public static function advice(string $advisorId): Closure
    {
        $value = self::getContainer()->getValue($advisorId);

        if ($value instanceof Advisor) {
            $value = $value->getAdvice();
        }
        if ($value instanceof AbstractInterceptor) {
            return $value->getRawAdvice();
        }
        if ($value instanceof Closure) {
            return $value;
        }

        throw new AspectException("Advisor {$advisorId} does not expose a closure advice");
    }

    private static function getContainer(): AspectContainer
    {
        return AspectKernel::getInstance()->getContainer();
    }
}
