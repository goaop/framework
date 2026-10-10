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

namespace Go\Stubs\ZEngine;

/**
 * Calls an advised internal function: unqualified (served by the namespaced proxy) and qualified (never intercepted)
 */
final class FunctionTarget
{
    public function encode(string $text): string
    {
        return str_rot13($text);
    }

    public function encodeQualified(string $text): string
    {
        return \str_rot13($text);
    }
}
