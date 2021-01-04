<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo;

use ReflectionFunction;

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
        $highlightFileFunc = new ReflectionFunction('highlight_file');
        if (!$highlightFileFunc->isDisabled()) {
            highlight_file($file);
        } else {
            echo '<pre>' . htmlspecialchars(file_get_contents($file)) . '</pre>';
        }
    }
}
