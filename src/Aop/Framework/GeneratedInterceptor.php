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

use Go\Aop\Advice;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use ReflectionFunction;

/**
 * Internal descriptor used by proxy generators to render first-class advice callables.
 *
 * @internal
 */
final readonly class GeneratedInterceptor
{
    private function __construct(
        public string $factoryMethod,
        public string $aspectClass,
        public string $adviceMethod,
        public int $order,
        public string $advisorId
    ) {}

    public static function fromAdvice(string $advisorId, Advice $advice): self
    {
        if (!$advice instanceof AbstractInterceptor) {
            throw new AspectException("Advisor {$advisorId} uses unsupported advice " . get_debug_type($advice) . '; only framework aspect-method interceptors can be generated');
        }

        $reflectionAdvice = new ReflectionFunction($advice->getRawAdvice());
        $scopeClass       = $reflectionAdvice->getClosureScopeClass();
        if ($scopeClass === null || !is_subclass_of($scopeClass->name, Aspect::class)) {
            throw new AspectException("Advisor {$advisorId} uses an unsupported non-aspect callable; generated first-class advice callables require aspect methods");
        }

        return new self(
            $advice->getType()->value,
            $scopeClass->name,
            $reflectionAdvice->name,
            $advice->getAdviceOrder(),
            $advisorId
        );
    }

    public static function fromAdvisorId(string $advisorId): self
    {
        $reference = str_starts_with($advisorId, 'advisor.') ? substr($advisorId, 8) : $advisorId;
        [$aspectClass, $adviceMethod] = str_contains($reference, '->')
            ? explode('->', $reference, 2)
            : [$reference, 'advice'];

        return new self('before', $aspectClass, $adviceMethod, 0, $advisorId);
    }
}
