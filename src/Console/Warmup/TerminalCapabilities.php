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

namespace Go\Console\Warmup;

use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Terminal;

/**
 * What the terminal behind an output can render
 *
 * @internal
 */
final readonly class TerminalCapabilities
{
    public function __construct(
        /** Whether the output is an interactive terminal that understands colors and cursor movements, so a progress bar can be redrawn in place */
        public bool $interactive,
        /** Whether the terminal renders Unicode characters */
        public bool $unicode,
        /** Width of the terminal, in columns */
        public int $width,
    ) {}

    /**
     * Detects the capabilities of the terminal behind the output
     *
     * A progress bar is redrawn in place only on a decorated output that is a terminal: a CI log or a file keeps
     * every redraw, and a terminal without ANSI support (TERM=dumb) can not move the cursor.
     *
     * @param string $osFamily Family of the operating system, PHP_OS_FAMILY
     */
    public static function detect(StreamOutput $output, string $osFamily = PHP_OS_FAMILY): self
    {
        $interactive = $output->isDecorated() && stream_isatty($output->getStream()) && getenv('TERM') !== 'dumb';

        return new self($interactive, self::supportsUnicode($osFamily), new Terminal()->getWidth());
    }

    private static function supportsUnicode(string $osFamily): bool
    {
        if ($osFamily === 'Windows') {
            // UTF-8 code page, Windows Terminal or the terminal of an IDE
            return (function_exists('sapi_windows_cp_get') && sapi_windows_cp_get() === 65001)
                || getenv('WT_SESSION') !== false
                || getenv('TERM_PROGRAM') !== false;
        }
        // The first locale variable that is set decides, like for the C library
        foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $name) {
            $locale = getenv($name);
            if (is_string($locale) && $locale !== '') {
                return preg_match('/utf-?8/i', $locale) === 1;
            }
        }

        return false;
    }
}
