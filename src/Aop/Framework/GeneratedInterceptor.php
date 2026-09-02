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
        public ?string $aspectClass,
        public ?string $adviceMethod,
        public int $order,
        public string $advisorId,
        public bool $usesContainerAdvice = false
    ) {}

    public static function fromAdvice(string $advisorId, Advice $advice): self
    {
        if (!$advice instanceof AbstractInterceptor) {
            throw new AspectException("Advisor {$advisorId} uses unsupported advice " . get_debug_type($advice) . '; only framework aspect-method interceptors can be generated');
        }

        $reflectionAdvice    = new ReflectionFunction($advice->getRawAdvice());
        $scopeClass          = $reflectionAdvice->getClosureScopeClass();
        $usesContainerAdvice = $scopeClass === null || !is_subclass_of($scopeClass->name, Aspect::class);

        return new self(
            $advice->getType()->value,
            $usesContainerAdvice ? null : $scopeClass->name,
            $usesContainerAdvice ? null : $reflectionAdvice->name,
            $advice->getAdviceOrder(),
            $advisorId,
            $usesContainerAdvice
        );
    }
}
