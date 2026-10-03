<?php
declare(strict_types=1);

return [

    'default' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    'production' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The cache warmed up with the 'production' configuration, moved to another directory and trusted as prebuilt
    'moved_cache' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-moved',
        'features' => \Go\Aop\Features::PREBUILT_CACHE,
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    'inconsistent_weaving' => [
        'kernel' => \Go\Tests\TestProject\Kernel\InconsistentlyWeavingAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],
];
