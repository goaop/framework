<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

include '../src/Go/Core/AspectKernel.php';
include 'DemoAspectKernel.php';

// Initialize demo aspect container
DemoAspectKernel::getInstance()->init();

$class = new Example('test');
$class->publicHello();
