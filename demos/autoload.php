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

/**
 * Register PSR-0 autoloader for our code, any components can be used here
 */
spl_autoload_register(function($originalClassName) {
    $className = ltrim($originalClassName, '\\');
    $fileName  = '';
    $namespace = '';
    if ($lastNsPos = strripos($className, '\\')) {
        $namespace = substr($className, 0, $lastNsPos);
        $className = substr($className, $lastNsPos + 1);
        $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
    }
    $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

    $resolvedFileName = stream_resolve_include_path($fileName);
    if ($resolvedFileName) {
        require_once $resolvedFileName;
    }
    return (bool) $resolvedFileName;
});

ob_start(function($content) {
    return str_replace(PHP_EOL, "<br>" . PHP_EOL, $content);
});