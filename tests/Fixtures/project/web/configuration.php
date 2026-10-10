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

    // The z-engine weaving driver: classes load natively and are rewired through the engine (tests/Functional/ZEngine)
    'zengine' => [
        'kernel' => \Go\Tests\TestProject\Kernel\ZEngineAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => 'zengine',
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-zengine',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    'zengine_production' => [
        'kernel' => \Go\Tests\TestProject\Kernel\ZEngineAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'driver' => 'zengine',
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-zengine-production',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The same aspects woven by the stream driver: the reference the z-engine driver's behaviour is compared with
    'zengine_reference' => [
        'kernel' => \Go\Tests\TestProject\Kernel\ZEngineAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => 'stream',
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-zengine-reference',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // An aspect with a property pointcut: the z-engine driver refuses the class it matches
    'zengine_unsupported' => [
        'kernel' => \Go\Tests\TestProject\Kernel\ZEngineUnsupportedAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => 'zengine',
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-zengine-unsupported',
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
