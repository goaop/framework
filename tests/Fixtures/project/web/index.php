<?php
declare(strict_types = 1);

include_once __DIR__ . '/../../../../vendor/autoload.php';

$configuration = ($env = getenv('GO_AOP_CONFIGURATION')) ? $env : 'default' ;
$settings = require __DIR__.'/configuration.php';

$applicationAspectKernel = $settings[$configuration]['kernel']::getInstance();
// The test-harness keys (kernel class, console and front-controller paths) are not kernel options
$applicationAspectKernel->init(array_diff_key($settings[$configuration], array_flip(['kernel', 'console', 'frontController'])));
