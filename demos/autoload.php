<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * Show all errors in code
 */
ini_set('display_errors', true);

// Composer autoloading
if (file_exists('../vendor/autoload.php')) {
    $loader = include '../vendor/autoload.php';
}

if (php_sapi_name()!='cli') {
    ob_start(function($content) {
        return str_replace(PHP_EOL, "<br>" . PHP_EOL, $content);
    });
}
