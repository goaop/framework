<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace go\core;

/**
 * @package go
 * @subpackage core
 */
class Autoload
{
    const CLASS_LOADER_FILTER_PREFIX = 'go.core.classloader.';

    static function init()
    {
        spl_autoload_register(self::getLoader());
        stream_filter_register(self::CLASS_LOADER_FILTER_PREFIX . '*', __NAMESPACE__ . '\\'. 'ClassLoader') or die('bad');
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
                $isCore = strpos($namespace, 'go\core') === 0;
                if (!$isCore) {
                    require "php://filter/read=".Autoload::CLASS_LOADER_FILTER_PREFIX."$originalClassName/resource=$resolvedFileName";
                } else {
                    require $resolvedFileName;
                }
            }
            return (bool)$resolvedFileName;
        };
    }

}
