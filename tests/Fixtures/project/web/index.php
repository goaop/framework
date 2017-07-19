<?php

include_once __DIR__ . '/../../../../vendor/autoload.php';

use Go\Tests\TestProject\ApplicationAspectKernel;

$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init(array(
    'appDir' => __DIR__ . '/../',
    'debug' => true,
    'cacheDir'  => __DIR__ . '/../var/cache/aspect',
    'includePaths' => array(
        __DIR__ . '/../src/'
    )
));

