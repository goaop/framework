<?php
declare(strict_types = 1);

include_once __DIR__ . '/../../../../vendor/autoload.php';

$configuration = ($env = getenv('GO_AOP_CONFIGURATION')) ? $env : 'default' ;
$settings = require __DIR__.'/configuration.php';

$applicationAspectKernel = $settings[$configuration]['kernel']::getInstance();
$applicationAspectKernel->init($settings[$configuration]);
