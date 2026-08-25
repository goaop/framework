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

    // Same application as 'default' but in production mode (warm-cache fast path),
    // with a separate cache directory so debug and production caches never mix.
    // Used by the boot-time benchmark harness in tests/Benchmark.
    'production' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-prod',
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
