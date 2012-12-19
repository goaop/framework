<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
ini_set('display_errors', 1);

if (defined("AUTOLOAD_PATH")) {
    if (is_file(realpath(AUTOLOAD_PATH))) {
        include_once AUTOLOAD_PATH;
    } else {
        throw new InvalidArgumentException("Cannot load custom autoload file located at ".AUTOLOAD_PATH);
    }
} else {

    // default autoload for library classes
    spl_autoload_register(function($class) {
        if (0 === strpos($class, 'Go\\')) {
            $path = __DIR__ . '/../src/' . implode('/', (explode('\\', $class))).'.php';
            if (!file_exists($path)) {
                return false;
            }

            require $path;

            if (!class_exists($class) && !interface_exists($class)) {
                return false;
            }
            return true;
        } else if (0 === strpos($class, 'TokenReflection\\')) {
            $path = __DIR__ . '/../vendor/andrewsville/php-token-reflection/' . implode('/', (explode('\\', $class))).'.php';
            if (!file_exists($path)) {
                return false;
            }

            require $path;

            if (!class_exists($class) && !interface_exists($class)) {
                return false;
            }
            return true;
        }
        return false;
    });
}
