<?php

return array(

    'default' => array(
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => array(
            __DIR__ . '/../src/'
        ),
    ),

    'inconsistent_weaving' => array(
        'kernel' => \Go\Tests\TestProject\Kernel\InconsistentlyWeavingAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => array(
            __DIR__ . '/../src/'
        ),
    ),
);
