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

use RuntimeException;

use function function_exists;

/**
 * Writes cache files to the file system in a safe and uniform way.
 *
 * Every cache artefact of the framework goes through this writer: woven sources and
 * class/function proxies (SourceTransformingLoader, WeavingTransformer), the cache
 * metadata maps (CachePathManager) and the compiled advisor caches (CachedAspectLoader).
 * It creates the target directory on demand, writes atomically (same-directory temporary
 * file + rename, so a concurrent `include` never sees a partially written file), strips
 * executable bits from the resulting file and invalidates a possibly cached opcode entry.
 *
 * @internal
 */
final readonly class CacheFileWriter
{
    /**
     * @param int $fileMode Binary mask of permission bits that is set to cache files
     */
    public function __construct(private int $fileMode) {}

    /**
     * Returns the permission bits for cache directories derived from the cache file mode
     *
     * Directories need the search (x) bit wherever the file mode grants read access,
     * otherwise a mode like 0644 would produce directories nobody can traverse.
     */
    public static function directoryModeFor(int $fileMode): int
    {
        return $fileMode | (($fileMode & 0444) >> 2);
    }

    /**
     * Writes the content into the file, creating the parent directory when needed
     *
     * @throws RuntimeException When the directory or the file can not be written
     */
    public function write(string $fileName, string $content): void
    {
        $directoryName = dirname($fileName);
        $directoryMode = self::directoryModeFor($this->fileMode);
        // A concurrent request may create the directory between the check and the mkdir() call,
        // so a failed mkdir() is only an error when the directory still does not exist.
        if (!is_dir($directoryName) && !@mkdir($directoryName, $directoryMode, true) && !is_dir($directoryName)) {
            throw new RuntimeException(sprintf('Unable to create cache directory "%s".', $directoryName));
        }

        // The temporary name is unique per call, so no file locking is needed: the content
        // becomes visible atomically through the same-directory rename. One universal code
        // path for plain files and stream wrapper paths (virtual file systems) alike.
        $temporaryName = $directoryName . '/' . basename($fileName) . '.' . bin2hex(random_bytes(8)) . '.tmp';
        if (@file_put_contents($temporaryName, $content) !== strlen($content)) {
            @unlink($temporaryName);
            throw new RuntimeException(sprintf('Unable to write cache file "%s".', $fileName));
        }
        // For cache files we don't want executable bits by default. Permissions are applied
        // before the rename, so the file never becomes visible with the default mode.
        @chmod($temporaryName, $this->fileMode & (~0111));
        if (!@rename($temporaryName, $fileName)) {
            @unlink($temporaryName);
            throw new RuntimeException(sprintf('Unable to move cache file into place "%s".', $fileName));
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($fileName, true);
        }
    }
}
