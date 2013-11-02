<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
ini_set('display_errors', 1);

if (defined("AUTOLOAD_PATH")) {
    if (is_file(__DIR__ . '/../' .AUTOLOAD_PATH)) {
        $loader = include_once __DIR__ . '/../' . AUTOLOAD_PATH;
        $loader->add('Go', __DIR__);
    } else {
        throw new InvalidArgumentException("Cannot load custom autoload file located at ".AUTOLOAD_PATH);
    }
}
