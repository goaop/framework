<?php

return array(

    'default' => array(
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => array(
            __DIR__ . '/../src/'
        ),
    ),

    'inconsistent_weaving' => array(
        'kernel' => \Go\Tests\TestProject\Kernel\InconsistentlyWeavingAspectKernel::class,
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => array(
            __DIR__ . '/../src/'
        ),
    ),
);
