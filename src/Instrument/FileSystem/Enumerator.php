<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\FileSystem;

/**
 * Enumerates files in the concrete directory, applying filtration logic
 */
class Enumerator
{
    /**
     * Path to the root directory, where enumeration should start
     *
     * @var string
     */
    private $rootDirectory;

    /**
     * List of additional include paths, should be below rootDirectory
     *
     * @var array
     */
    private $includePaths;

    /**
     * List of additional exclude paths, should be below rootDirectory
     *
     * @var array
     */
    private $excludePaths;

    /**
     * Initializes an enumerator
     *
     * @param string $rootDirectory Path to the root directory
     * @param array $includePaths List of additional include paths
     * @param array $excludePaths List of additional exclude paths
     */
    public function __construct($rootDirectory, array $includePaths = [], array $excludePaths = [])
    {
        $this->rootDirectory = $rootDirectory;
        $this->includePaths  = $includePaths;
        $this->excludePaths  = $excludePaths;
    }

    /**
     * Returns an enumerator for files
     *
     * @return \CallbackFilterIterator|\RecursiveIteratorIterator|\SplFileInfo[]
     */
    public function enumerate()
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->rootDirectory,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        $callback = $this->getFilter();
        $iterator = new \CallbackFilterIterator($iterator, $callback);

        return $iterator;
    }

    /**
     * Returns a filter callback for enumerating files
     *
     * @return \Closure
     */
    public function getFilter()
    {
        $rootDirectory = $this->rootDirectory;
        $includePaths  = $this->includePaths;
        $excludePaths  = $this->excludePaths;

        return function(\SplFileInfo $file) use ($rootDirectory, $includePaths, $excludePaths) {
            if ($file->getExtension() !== 'php') {
                return false;
            };

            $realPath = $file->getRealPath();
            // Do not touch files that not under rootDirectory
            if (strpos($realPath, $rootDirectory) !== 0) {
                return false;
            }

            if (!empty($includePaths)) {
                $found = false;
                foreach ($includePaths as $includePath) {
                    if (strpos($realPath, $includePath) === 0) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return false;
                }
            }

            foreach ($excludePaths as $excludePath) {
                if (strpos($realPath, $excludePath) === 0) {
                    return false;
                }
            }

            return true;
        };
    }
}
