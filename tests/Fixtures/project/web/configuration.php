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

    // Production without weaving the aspects: their classes are always loaded natively, so the aspects register in
    // the same order whether the cache is warm or empty (issue #807)
    'production_native_aspects' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => false,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'includePaths' => [
            __DIR__ . '/../src/'
        ],
        'excludePaths' => [
            __DIR__ . '/../src/Aspect/'
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

    // Weaves a directory with a class whose ancestors can't be located (goaop/parser-reflection#251)
    'partial_reflection' => [
        'kernel' => \Go\Tests\TestProject\Kernel\DefaultAspectKernel::class,
        'console' => __DIR__ . '/../bin/console',
        'frontController' => __DIR__ . '/../web/index.php',
        'appDir' => __DIR__ . '/../',
        'debug' => true,
        'cacheDir'  => __DIR__ . '/../var/cache/aspect',
        'features' => \Go\Aop\Features::PARTIAL_REFLECTION,
        'includePaths' => [
            __DIR__ . '/../src/',
            __DIR__ . '/../partial/',
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
