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

namespace Go\Proxy\Generator\Stubs;

use Exception;

function funcGenHelper_simple(string $name, int $count = 0): string
{
    return str_repeat($name, $count);
}

function funcGenHelper_nullable(?string $x = null): ?string
{
    return $x;
}

function funcGenHelper_variadic(string ...$items): array
{
    return $items;
}

function funcGenHelper_void(): void {}

function funcGenHelper_classReturn(): Exception
{
    return new Exception();
}

#[\Deprecated]
function funcGenHelper_deprecated(): void {}

function funcGenHelper_noAttr(): void {}
