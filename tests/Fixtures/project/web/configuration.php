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

    // Includes inside woven files are intercepted (issue #746)
    'production_includes' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'features' => \Go\Aop\Features::INTERCEPT_INCLUDES,
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The cache warmed up with the 'production_includes' configuration, moved to another directory and trusted as prebuilt
    'moved_includes_cache' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-moved',
        'features' => \Go\Aop\Features::INTERCEPT_INCLUDES | \Go\Aop\Features::PREBUILT_CACHE,
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The ffi weaving driver: classes load natively and are rewired through the engine (packages/ffi-driver/tests/Functional)
    'ffi' => [
        'kernel' => \Go\Tests\TestProject\Kernel\FfiAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => \Go\Driver\Ffi\FfiDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-ffi',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    'ffi_production' => [
        'kernel' => \Go\Tests\TestProject\Kernel\FfiAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'driver' => \Go\Driver\Ffi\FfiDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-ffi-production',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The same aspects woven by the stream driver: the reference the ffi driver's behaviour is compared with
    'ffi_reference' => [
        'kernel' => \Go\Tests\TestProject\Kernel\FfiAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => \Go\Driver\StreamFilter\StreamFilterDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-ffi-reference',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // An aspect with a property pointcut: the ffi driver refuses the class it matches
    'ffi_unsupported' => [
        'kernel' => \Go\Tests\TestProject\Kernel\FfiUnsupportedAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => \Go\Driver\Ffi\FfiDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-ffi-unsupported',
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

    // The stream wrapper weaving driver: every include within the scope is woven through the replaced file:// wrapper
    // (packages/stream-wrapper-driver/tests/Functional)
    'stream_wrapper' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => \Go\Driver\StreamWrapper\StreamWrapperDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-stream-wrapper',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    'stream_wrapper_production' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'driver' => \Go\Driver\StreamWrapper\StreamWrapperDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-stream-wrapper-production',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],

    // The ffi driver fixtures woven by the stream wrapper driver: compared with 'ffi_reference' (the stream filter driver)
    'stream_wrapper_methods' => [
        'kernel' => \Go\Tests\TestProject\Kernel\FfiAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'driver' => \Go\Driver\StreamWrapper\StreamWrapperDriver::class,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect-stream-wrapper-methods',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
    ],
];
