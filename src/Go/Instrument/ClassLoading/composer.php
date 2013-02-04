<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2013, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\ClassLoading;

use Go\Instrument\ClassLoading\UniversalClassLoader;

/**
* Ensures that autoloader of Go! library is first in the stack of autoloaders
*
* This fix problems with autoloaders that was prepended, for example, composer.
*
* @return void
*/
function ensureLibraryAutoloaderIsFirst()
{
    $loaders = spl_autoload_functions();
    if ($loaders && isset($loaders[0]) && is_array($loaders[0])) {
        if ($loaders[0][0] instanceof UniversalClassLoader) {
            return;
        }
    }
    $newLoaders = array();
    foreach ($loaders as $loader) {
        spl_autoload_unregister($loader);
        if (is_array($loader) && ($loader[0] instanceof UniversalClassLoader)) {
            array_unshift($newLoaders, $loader);
        } else {
            array_push($newLoaders, $loader);
        }
    }
    foreach ($newLoaders as $loader) {
        spl_autoload_register($loader);
    }
}

ensureLibraryAutoloaderIsFirst();