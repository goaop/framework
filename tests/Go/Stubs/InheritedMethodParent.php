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

class InheritedMethodParent
{
    public function inheritedPublicMethod(): int
    {
        return T_PUBLIC;
    }

    public static function inheritedStaticMethod(): int
    {
        return T_PUBLIC;
    }

    /**
     * @return array{class-string, class-string}
     */
    public static function inheritedStaticLsbMethod(): array
    {
        return [static::class, get_called_class()];
    }
}
