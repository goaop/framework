# The stream wrapper driver

Package `goaop/stream-wrapper-driver`, class `Go\Driver\StreamWrapper\StreamWrapperDriver`, a
`Go\Driver\SourceTransformingDriver`. Pure PHP, no extension.

```bash
composer require goaop/stream-wrapper-driver
```

```php
use Go\Driver\StreamWrapper\StreamWrapperDriver;

ApplicationAspectKernel::getInstance()->init([
    'driver'       => StreamWrapperDriver::class,
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

## Mechanism

`boot()` replaces PHP's `file://` stream wrapper (`stream_wrapper_unregister('file')`,
`stream_wrapper_register('file', AopStreamWrapper::class)`). From then on every `include`/`require` of a file within
the weaving scope (`appDir`, `includePaths`, `excludePaths`), whoever issues it (composer's loader, a `require` in
legacy code, a template engine), is served transformed through the same entry point the stream filter driver uses,
`Go\Instrument\ClassLoading\SourceTransformingLoader::transformSource()`: a usable cache record serves the cached
woven source, a miss runs the transformer chain and persists the result. Files outside the scope and every other file
operation pass through to the real file system. No composer loader wrapper is installed.

The woven source is served under the **original path**, so opcache caches it like any other file (an include through
`php://filter`, the stream filter driver's cache-miss path, is never cached by opcache). The cache layout below
`cacheDir` and the `_transformation.cache` index are those of the [stream filter driver](stream-filter.md), and
`bin/aspect cache:warmup:aop` builds the cache ahead of time for both.

## Supported join points

Every join point of the stream filter driver: method execution, functions, property access, introductions,
initialization join points, enums and traits. Includes are always intercepted: the wrapper sees every include, so
`Features::INTERCEPT_INCLUDES` is implied and refused in the `features` option (`InvalidConfigurationException` from
`init()`).

## Trade-offs

- Every include within the scope is woven, including files that composer never loads (legacy `require` trees), with
  no rewriting of the include statements.
- The price: every file operation of the process (`fopen`, `file_get_contents`, `is_file`, `stat`, directory listings,
  every include outside the scope too) passes through userland PHP while the wrapper is registered. Keep
  `includePaths` narrow and measure.
- Code that registers its own `file://` wrapper, or that unregisters wrappers, conflicts with the driver.
