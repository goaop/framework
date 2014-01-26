<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
