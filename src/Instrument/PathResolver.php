<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument;

/**
 * Special class for resolving path for different file systems, wrappers, etc
 *
 * @see http://stackoverflow.com/questions/4049856/replace-phps-realpath/4050444
 * @see http://bugs.php.net/bug.php?id=52769
 */
class PathResolver
{

    /**
     * Custom replacement for realpath() and stream_resolve_include_path()
     *
     * @param string|array $somePath Path without normalization or array of paths
     * @param bool $shouldCheckExistence Flag for checking existence of resolved filename
     *
     * @return array|bool|string
     */
    public static function realpath($somePath, $shouldCheckExistence = false)
    {
        // Do not resolve empty string/false/arrays into the current path
        if (!$somePath) {
            return $somePath;
        }

        if (is_array($somePath)) {
            return array_map(array(__CLASS__, __FUNCTION__), $somePath);
        }
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $somePath, 2);
        list ($pathScheme, $path) = isset($components[1]) ? $components : array(null, $components[0]);

        // Optimization to bypass complex logic for simple paths (eg. not in phar archives)
        if (!$pathScheme && ($fastPath = stream_resolve_include_path($somePath))) {
            return $fastPath;
        }

        $isRelative = !$pathScheme && ($path[0] !== '/') && ($path[1] !== ':');
        if ($isRelative) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        // resolve path parts (single dot, double dot and double delimiters)
        $path = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        if (strpos($path, '.') !== false) {
            $parts     = explode(DIRECTORY_SEPARATOR, $path);
            $absolutes = [];
            foreach ($parts as $part) {
                if ('.' == $part) {
                    continue;
                } elseif ('..' == $part) {
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
}
