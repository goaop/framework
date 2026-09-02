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

namespace Go\Core\Cache;

use function function_exists;

/**
 * Writes cache files to the file system in a safe and uniform way.
 *
 * Every cache artefact of the framework goes through this writer: it creates the
 * target directory on demand, writes atomically where the file system allows it
 * (same-directory temporary file + rename), strips executable bits from the
 * resulting file and invalidates a possibly cached opcode entry.
 */
final readonly class CacheFileWriter
{
    /**
     * @param int $fileMode Binary mask of permission bits that is set to cache files
     */
    public function __construct(private int $fileMode) {}

    /**
     * Writes the content into the file, creating the parent directory when needed
     */
    public function write(string $fileName, string $content): void
    {
        $directoryName = dirname($fileName);
        if (!is_dir($directoryName)) {
            mkdir($directoryName, $this->fileMode, true);
        }

        // PHP core refuses the LOCK_EX flag for any non-"file://" stream wrapper path and
        // rename() semantics are wrapper-specific, so virtual file systems get a plain write
        $isStreamPath = str_contains($fileName, '://');
        if ($isStreamPath) {
            file_put_contents($fileName, $content);
        } else {
            $temporaryName = $directoryName . '/' . uniqid(basename($fileName), true) . '.tmp';
            file_put_contents($temporaryName, $content, LOCK_EX);
            rename($temporaryName, $fileName);
        }
        // For cache files we don't want executable bits by default
        chmod($fileName, $this->fileMode & (~0111));

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fileName, true);
        }
    }
}
