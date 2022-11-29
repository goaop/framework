<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015-2022, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo;

/**
 * Highlighter utility class
 */
final class Highlighter
{
    /**
     * Highlighter with built-in check for list of disabled function (Google AppEngine)
     *
     * @param string $file Name of the file
     */
    public static function highlight(string $file): void
    {
        highlight_file($file);
    }
}
