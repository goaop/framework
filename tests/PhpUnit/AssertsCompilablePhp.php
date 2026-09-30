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

namespace Go\PhpUnit;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Checks generated source with `php -l` in a child process.
 *
 * A parser alone is not enough: compile-time errors such as duplicate `use` aliases
 * ("Cannot use X as Y because the name is already in use") are only reported by the
 * engine, and they are fatal, so they can not be caught by including the file in-process.
 * The source is passed over stdin, no temporary file is needed.
 */
trait AssertsCompilablePhp
{
    protected static function assertPhpCompiles(string $source, string $message = ''): void
    {
        $php = (new PhpExecutableFinder())->find(false);
        self::assertIsString($php, 'PHP binary is not found');

        $process = new Process([$php, '-d', 'display_errors=stderr', '-l']);
        $process->setInput($source);
        $process->run();

        self::assertSame(
            0,
            $process->getExitCode(),
            ($message !== '' ? $message . PHP_EOL : '') . $process->getErrorOutput() . $process->getOutput() . PHP_EOL . $source,
        );
    }
}
