<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Core;

use Go\Instrument\ClassLoading\SourceTransformingLoader;

/**
 * @package go
 * @subpackage core
 */
class Autoload
{
    /**
     * Will be deprecated soon or used only for Go classes
     */
    static function init()
    {
        spl_autoload_register(self::getLoader());
    }

    protected static function getLoader()
    {
        return function($originalClassName) {
            $className = ltrim($originalClassName, '\\');
            $fileName  = '';
            $namespace = '';
            if ($lastNsPos = strripos($className, '\\')) {
                $namespace = substr($className, 0, $lastNsPos);
                $className = substr($className, $lastNsPos + 1);
                $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
            }
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

            $resolvedFileName = $fileName;//stream_resolve_include_path($fileName);
            if ($resolvedFileName) {
                $isGo = strpos($originalClassName, 'Go\\') === 0 || strpos($originalClassName, 'TokenReflection\\') === 0;
                if (!$isGo) {
                    SourceTransformingLoader::load($resolvedFileName);
                } else {
                    require $resolvedFileName;
                }
            }
            return (bool) $resolvedFileName;
        };
    }

}
