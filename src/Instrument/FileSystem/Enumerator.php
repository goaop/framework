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

use ArrayIterator;
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
     * @return Iterator<SplFileInfo>
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function enumerate(): Iterator
    {
        $finder = new Finder();
        $finder->files()
            ->name('*.php')
            ->in($this->getInPaths())
            // The same filter as the runtime loader, so warmup and autoloading weave exactly the same files
            ->filter($this->getFilter());

        $iterator = $finder->getIterator();

        // on Windows platform the default iterator is unable to rewind, not sure why
        if (PHP_OS_FAMILY === 'Windows') {
            $iterator = new ArrayIterator(iterator_to_array($iterator));
        }

        return $iterator;
    }

    /**
     * Returns a filter callback for enumerating files
     */
    public function getFilter(): Closure
    {
        // Every include/exclude path is a literal path prefix where only `*` is a wildcard (also crossing
        // directory separators); `\` and `/` are treated alike. Patterns are compiled once per filter.
        $includeRegexps = array_map(self::toPrefixRegexp(...), $this->includePaths);
        $excludeRegexps = array_map(self::toPrefixRegexp(...), $this->excludePaths);

        return function (SplFileInfo $file) use ($includeRegexps, $excludeRegexps): bool {
            if ($file->getExtension() !== 'php') {
                return false;
            }

            $fullPath = $this->getFileFullPath($file);
            // Do not touch files that not under rootDirectory
            if (!PathResolver::isBelow($fullPath, $this->rootDirectory)) {
                return false;
            }

            $normalizedPath = str_replace('\\', '/', $fullPath);
            $matchesPattern = static fn(string $regexp): bool => preg_match($regexp, $normalizedPath) === 1;

            if ($includeRegexps !== [] && !array_any($includeRegexps, $matchesPattern)) {
                return false;
            }

            return !array_any($excludeRegexps, $matchesPattern);
        };
    }

    /**
     * Converts a path pattern into an anchored regular expression matching the path and everything below it
     */
    private static function toPrefixRegexp(string $pattern): string
    {
        $quotedPattern = preg_quote(str_replace('\\', '/', $pattern), '#');

        return '#^' . str_replace('\\*', '.*', $quotedPattern) . '#';
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
