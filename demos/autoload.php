<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Show all errors in code
 */
ini_set('display_errors', true);

// Composer autoloading
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    /** @var Composer\Autoload\ClassLoader $loader */
    $loader = include __DIR__ . '/../vendor/autoload.php';
    $loader->add('Demo', __DIR__);
}