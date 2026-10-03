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

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Real-disk directories for the few tests that can not run on the virtual file system (see tests/AGENTS.md).
 *
 * Every directory is unique per process and call, so parallel or repeated runs never share state, and its
 * path is resolved with realpath() (e.g. /var → /private/var on macOS), so it matches the paths the code
 * under test resolves itself. Removal is recursive, which also drops the cache state files the code under
 * test writes, but it only ever touches directories created by this trait below the temporary directory.
 */
trait UsesTemporaryDirectory
{
    private const string TEMPORARY_DIRECTORY_PREFIX = 'goaop-test-';

    protected static function createTemporaryDirectory(string $purpose): string
    {
        $directory = self::temporaryDirectoryRoot() . '/' . self::TEMPORARY_DIRECTORY_PREFIX . $purpose
            . '-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create temporary directory {$directory}");
        }

        // Native separators: the code under test compares these paths with realpath() results
        return self::temporaryDirectoryRoot() . DIRECTORY_SEPARATOR . basename($directory);
    }

    protected static function removeTemporaryDirectory(string $directory): void
    {
        $realDirectory = realpath($directory);
        if ($realDirectory === false || !is_dir($realDirectory)) {
            return;
        }
        // Refuse to touch anything that was not created by createTemporaryDirectory(); realpath()
        // uses the native directory separator, so the path is compared by its parts
        if (dirname($realDirectory) !== self::temporaryDirectoryRoot()
            || !str_starts_with(basename($realDirectory), self::TEMPORARY_DIRECTORY_PREFIX)
        ) {
            throw new RuntimeException("Refusing to remove {$realDirectory}: not a test temporary directory");
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($realDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($realDirectory);
    }

    private static function temporaryDirectoryRoot(): string
    {
        $root = realpath(sys_get_temp_dir());
        if ($root === false) {
            throw new RuntimeException('The system temporary directory does not exist');
        }

        return $root;
    }
}
