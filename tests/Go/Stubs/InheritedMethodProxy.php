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

namespace Go\Stubs;

class InheritedMethodProxy extends InheritedMethodParent
{
    private const int OVERRIDDEN_SENTINEL = -1;

    public function inheritedPublicMethod(): int
    {
        return self::OVERRIDDEN_SENTINEL;
    }

    public static function inheritedStaticMethod(): int
    {
        return self::OVERRIDDEN_SENTINEL;
    }

    /**
     * @return array{class-string, class-string}
     */
    public static function inheritedStaticLsbMethod(): array
    {
        return [self::class, self::class];
    }
}
