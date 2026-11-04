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
use Go\Aop\Exception\AspectException;

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

        // An advice given by name is read without its closure: reading it would create the aspect. Any closure that
        // is not an aspect method is referenced through the container: The::advice('<advisor id>')
        $aspectMethod = $advice->aspectMethod ?? AbstractInterceptor::aspectMethodOf($advice->getRawAdvice());
        [$aspectClass, $adviceMethod] = $aspectMethod ?? [null, null];
        $usesContainerAdvice          = $aspectMethod === null;

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
