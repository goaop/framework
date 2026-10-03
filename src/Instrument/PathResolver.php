<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

use function is_array;

/**
 * Resolves paths for different file systems and stream wrappers without touching the disk,
 * which native realpath() can not do for stream wrapper (e.g. phar://, vfs://) paths
 */
final class PathResolver
{
    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Custom replacement for realpath() and stream_resolve_include_path()
     *
     * @param string|string[] $somePath Path without normalization or array of paths
     * @param bool $shouldCheckExistence Flag for checking existence of resolved filename
     *
     * @return ($somePath is array ? list<string|false> : string|false)
     */
    public static function realpath(string|array $somePath, bool $shouldCheckExistence = false): string|array|false
    {
        // Do not resolve empty string/empty arrays into the current path
        if (!$somePath) {
            return $somePath;
        }

        if (is_array($somePath)) {
            return array_values(array_map(self::realpath(...), $somePath));
        }
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $somePath, 2);
        if (isset($components[1])) {
            $pathScheme = $components[0];
            $path       = $components[1];
        } else {
            $pathScheme = null;
            $path       = $components[0];
        }

        // Optimization to bypass complex logic for simple paths (eg. not in phar archives)
        if (!$pathScheme && ($fastPath = stream_resolve_include_path($somePath))) {
            return $fastPath;
        }

        $isRelative = !$pathScheme && ($path[0] !== '/') && ($path[1] !== ':');
        if ($isRelative) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        // resolve path parts (single dot, double dot and double delimiters)
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (str_contains($path, '.')) {
            $parts     = explode(DIRECTORY_SEPARATOR, $path);
            $absolutes = [];
            foreach ($parts as $part) {
                if ('.' === $part) {
                    continue;
                }
                if ('..' === $part) {
                    array_pop($absolutes);
                } else {
                    $absolutes[] = $part;
                }
            }
            $path = implode(DIRECTORY_SEPARATOR, $absolutes);
        }

        if ($pathScheme) {
            $path = "{$pathScheme}://{$path}";
        }

        if ($shouldCheckExistence && !file_exists($path)) {
            return false;
        }

        return $path;
    }

    /**
     * Moves a path from one base directory to another
     *
     * Only a leading $fromDirectory followed by a directory separator (or the end of the path) is replaced:
     * occurrences of the directory elsewhere in the path and siblings sharing a name prefix (`/var/www-old`
     * for `/var/www`) are left alone.
     *
     * @return string|null The rebased path, or null when the path is not below $fromDirectory
     */
    public static function rebase(string $path, string $fromDirectory, string $toDirectory): ?string
    {
        if ($fromDirectory === '') {
            return null;
        }
        // The file-system root `/` becomes an empty prefix: every absolute path lies below it
        $fromDirectory = rtrim($fromDirectory, '/\\');
        if (!str_starts_with($path, $fromDirectory)) {
            return null;
        }
        $relativePart = substr($path, strlen($fromDirectory));
        if ($relativePart !== '' && $relativePart[0] !== '/' && $relativePart[0] !== '\\') {
            return null;
        }

        return rtrim($toDirectory, '/\\') . $relativePart;
    }

    /**
     * Checks that the path is the directory itself or lies below it
     */
    public static function isBelow(string $path, string $directory): bool
    {
        return self::rebase($path, $directory, '') !== null;
    }

    /**
     * Inserts a suffix in front of the file extension: `Foo.php` becomes `FooOriginalTrait.php`
     *
     * Only the trailing extension is touched, so directories like `lib.php/` keep their names.
     */
    public static function withSuffixBeforeExtension(string $path, string $suffix, string $extension = '.php'): string
    {
        if (!str_ends_with($path, $extension)) {
            return $path . $suffix;
        }

        return substr($path, 0, -strlen($extension)) . $suffix . $extension;
    }
}
