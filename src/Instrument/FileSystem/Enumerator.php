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

namespace Go\Instrument\FileSystem;

use Closure;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Instrument\PathResolver;
use InvalidArgumentException;
use Iterator;
use LogicException;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use UnexpectedValueException;

/**
 * Enumerates files in the concrete directory, applying filtration logic
 *
 * @internal Framework service, not a public extension point
 */
class Enumerator
{
    /**
     * Initializes an enumerator
     *
     * @param string   $rootDirectory Path to the root directory, where enumeration should start
     * @param string[] $includePaths  List of additional include paths, should be below rootDirectory
     * @param string[] $excludePaths  List of additional exclude paths, should be below rootDirectory
     */
    public function __construct(
        private readonly string $rootDirectory,
        private readonly array $includePaths = [],
        private readonly array $excludePaths = [],
    ) {}

    /**
     * Returns an enumerator for files
     *
     * The directory tree is walked lazily: collect the iterator (iterator_to_array()) to count and process the files,
     * it can not be rewound with several include paths and rewinding it would walk the tree again anyway.
     *
     * @return Iterator<SplFileInfo>
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function enumerate(): Iterator
    {
        $isAllowedPath     = $this->getPathFilter();
        $isTraversablePath = $this->getDirectoryFilter();

        $finder = new Finder();
        $finder->files()
            ->name('*.php')
            ->in($this->getInPaths())
            // Files: the same filter as the runtime loader, so warmup and autoloading weave exactly the same files.
            // Directories (only those reach the closure as prune filter): not entered when no file below them can pass
            ->filter(
                fn(SplFileInfo $file): bool => $file->isDir()
                    ? $isTraversablePath($this->getFileFullPath($file))
                    : $isAllowedPath($this->getFileFullPath($file)),
                prune: true,
            );

        return $finder->getIterator();
    }

    /**
     * Returns a filter callback for enumerating files
     *
     * @return Closure(SplFileInfo): bool
     */
    public function getFilter(): Closure
    {
        $isAllowedPath = $this->getPathFilter();

        return fn(SplFileInfo $file): bool => $isAllowedPath($this->getFileFullPath($file));
    }

    /**
     * Returns a filter callback for resolved file paths: the class loader checks with it every file it loads
     *
     * Every include/exclude path is a literal path prefix where only `*` is a wildcard (also crossing
     * directory separators); `\` and `/` are treated alike. Patterns are compiled once per filter, all
     * include paths and all exclude paths into one regular expression each.
     *
     * @return Closure(string): bool
     */
    public function getPathFilter(): Closure
    {
        // The same check as PathResolver::isBelow() with the root prepared once: an empty root contains nothing,
        // the file-system root `/` becomes an empty prefix, so every absolute path lies below it
        $rootPrefix    = rtrim($this->rootDirectory, '/\\');
        $rootLength    = strlen($rootPrefix);
        $hasRoot       = $this->rootDirectory !== '';
        $includeRegexp = self::toPrefixRegexp($this->includePaths);
        $excludeRegexp = self::toPrefixRegexp($this->excludePaths);

        return static function (string $fullPath) use ($rootPrefix, $rootLength, $hasRoot, $includeRegexp, $excludeRegexp): bool {
            if (!str_ends_with($fullPath, '.php') || !$hasRoot || !str_starts_with($fullPath, $rootPrefix)) {
                return false;
            }
            // Do not touch files that not under rootDirectory, siblings sharing a name prefix included
            $separator = $fullPath[$rootLength] ?? '';
            if ($separator !== '' && $separator !== '/' && $separator !== '\\') {
                return false;
            }

            $normalizedPath = str_replace('\\', '/', $fullPath);
            if ($includeRegexp !== null && preg_match($includeRegexp, $normalizedPath) !== 1) {
                return false;
            }

            return $excludeRegexp === null || preg_match($excludeRegexp, $normalizedPath) !== 1;
        };
    }

    /**
     * Returns a filter callback for resolved directory paths: false when no file below the directory can pass
     * the path filter, so the directory does not need to be walked
     *
     * The answer is conservative, a directory is only rejected when that holds for any file name below it:
     * - outside of the root directory;
     * - no include path can match below it: the literal part of an include path before its first `*` is neither
     *   a prefix of the directory nor below it;
     * - an exclude path matches the directory path with a trailing separator, so it matches a prefix of every path
     *   below the directory (a `*` pattern matching only some of the files below never rejects the directory).
     *
     * @see getPathFilter()
     *
     * @return Closure(string): bool
     */
    public function getDirectoryFilter(): Closure
    {
        $rootPrefix      = rtrim($this->rootDirectory, '/\\');
        $rootLength      = strlen($rootPrefix);
        $hasRoot         = $this->rootDirectory !== '';
        $excludeRegexp   = self::toPrefixRegexp($this->excludePaths);
        $includePrefixes = null;
        if ($this->includePaths !== []) {
            $includePrefixes = array_map(
                static fn(string $pattern): string => explode('*', str_replace('\\', '/', $pattern), 2)[0],
                $this->includePaths,
            );
        }

        return static function (string $directoryPath) use ($rootPrefix, $rootLength, $hasRoot, $excludeRegexp, $includePrefixes): bool {
            if (!$hasRoot) {
                return false;
            }
            // Every file below lies in `<directory>/`: the root check of a directory at least as long as the root
            // is the root check of all of them; a shorter directory (above the root) is never rejected by it
            $directoryPath = rtrim($directoryPath, '/\\');
            if (strlen($directoryPath) >= $rootLength) {
                $separator = $directoryPath[$rootLength] ?? '';
                if (!str_starts_with($directoryPath, $rootPrefix) || ($separator !== '' && $separator !== '/' && $separator !== '\\')) {
                    return false;
                }
            }

            $pathPrefix = str_replace('\\', '/', $directoryPath) . '/';
            if ($includePrefixes !== null) {
                $canBeIncluded = false;
                foreach ($includePrefixes as $includePrefix) {
                    if (str_starts_with($pathPrefix, $includePrefix) || str_starts_with($includePrefix, $pathPrefix)) {
                        $canBeIncluded = true;
                        break;
                    }
                }
                if (!$canBeIncluded) {
                    return false;
                }
            }

            return $excludeRegexp === null || preg_match($excludeRegexp, $pathPrefix) !== 1;
        };
    }

    /**
     * Converts path patterns into one anchored regular expression matching the paths and everything below them
     *
     * @param string[] $patterns
     *
     * @return string|null Null when there are no patterns
     */
    private static function toPrefixRegexp(array $patterns): ?string
    {
        if ($patterns === []) {
            return null;
        }
        $alternatives = array_map(
            static fn(string $pattern): string => str_replace('\\*', '.*', preg_quote(str_replace('\\', '/', $pattern), '#')),
            $patterns,
        );

        return '#^(?:' . implode('|', $alternatives) . ')#';
    }

    /**
     * Returns the real path of the given file
     *
     * Stream wrappers (phar://, vfs://) have no real path, their path name is used as is.
     */
    protected function getFileFullPath(SplFileInfo $file): string
    {
        return $file->getRealPath() ?: $file->getPathname();
    }

    /**
     * Returns collection of directories to look at
     *
     * @return string[]
     * @throws UnexpectedValueException if directory not under the root
     */
    private function getInPaths(): array
    {
        $inPaths = [];

        foreach ($this->includePaths as $path) {
            // Include paths must be below the root directory: this is a prefix check,
            // a path merely containing the root somewhere else must be rejected
            if (!PathResolver::isBelow($path, $this->rootDirectory)) {
                throw new InvalidConfigurationException(sprintf('Path %s is not in %s', $path, $this->rootDirectory));
            }

            $inPaths[] = $path;
        }

        if (empty($inPaths)) {
            $inPaths[] = $this->rootDirectory;
        }

        return $inPaths;
    }
}
