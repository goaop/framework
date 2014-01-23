<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2014, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
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
    public static function realpath($somePath, $shouldCheckExistence = false)
    {
        $normalized = null;
        if (is_array($somePath)) {
            return array_map(array(__CLASS__, __FUNCTION__), $somePath);
        }
        // Trick to get scheme name and path in one action. If no scheme, then there will be only one part
        $components = explode('://', $somePath, 2);
        list ($pathScheme, $path) = isset($components[1]) ? $components : array(null, $components[0]);

        // resolve path parts (single dot, double dot and double delimiters)
        $path  = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
        if (strpos($path, '.') !== false) {
            $parts     = array_filter(explode(DIRECTORY_SEPARATOR, $path));
            $absolutes = array();
            foreach ($parts as $part) {
                if ('.' == $path) {
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
