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
use Go\Aop\Exception\AspectException;
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
        public bool $usesContainerAdvice = false,
    ) {}

    public static function fromAdvice(string $advisorId, Advice $advice): self
    {
        if (!$advice instanceof AbstractInterceptor) {
            throw AspectException::unsupportedAdvice($advisorId, $advice);
        }

        if ($advice->aspectMethod !== null) {
            // An advice given by name: reading its closure would create the aspect
            [$aspectClass, $adviceMethod] = $advice->aspectMethod;
            $usesContainerAdvice          = false;
        } else {
            $reflectionAdvice    = new ReflectionFunction($advice->getRawAdvice());
            $scopeClass          = $reflectionAdvice->getClosureScopeClass();
            $usesContainerAdvice = $scopeClass === null || !is_subclass_of($scopeClass->name, Aspect::class);
            $aspectClass         = $usesContainerAdvice ? null : $scopeClass->name;
            $adviceMethod        = $usesContainerAdvice ? null : $reflectionAdvice->name;
        }

        return new self(
            $advice->getType()->value,
            $aspectClass,
            $adviceMethod,
            $advice->adviceOrder,
            $advisorId,
            $usesContainerAdvice,
        );
    }
}
