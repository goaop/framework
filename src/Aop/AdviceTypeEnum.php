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

namespace Go\Aop;

/**
 * Advice type enumeration
 *
 * @api
 */
enum AdviceTypeEnum: string
{
    case After = 'after';
    case AfterThrowing = 'afterThrowing';
    case Around = 'around';
    case Before = 'before';
    case Introduction = 'introduction';

    /**
     * Compares the relative invocation priority against another advice type.
     *
     * Advices execute in the order before -> after (and after-throwing) -> around, matching the
     * classic AOP interceptor chain where "around" wraps everything else.
     *
     * @api
     */
    public function compareTo(self $other): int
    {
        return $this->sortWeight() <=> $other->sortWeight();
    }

    private function sortWeight(): int
    {
        return match ($this) {
            self::Before => 0,
            self::After, self::AfterThrowing => 1,
            self::Around => 2,
            self::Introduction => 3,
        };
    }
}
