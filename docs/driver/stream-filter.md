# The stream filter driver (default)

Built into `goaop/framework`, class `Go\Driver\StreamFilter\StreamFilterDriver`, a `Go\Driver\SourceTransformingDriver`.
Pure PHP, no extension, no further package: it is what the kernel uses when the `driver` option is omitted.

```php
ApplicationAspectKernel::getInstance()->init([
    'driver'       => Go\Driver\StreamFilter\StreamFilterDriver::class, // may be omitted
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

## Mechanism

1. `boot()` wraps composer's class loader with `Go\Instrument\ClassLoading\AopComposerLoader` (a
   `ComposerLoaderDecorator`). Only classes loaded through composer are seen; a file `require`d directly is loaded as
   is unless includes are intercepted (`Features::INTERCEPT_INCLUDES`, see [configuration](../configuration.md)).
2. For a class whose file lies within the weaving scope (`appDir`, `includePaths`, `excludePaths`), the loader
   redirects the include through the `php://filter/read=go.source.transforming.loader/resource=<file>` stream filter
   (`Go\Instrument\ClassLoading\SourceTransformingLoader`). In production a class recorded as woven is included from
   its cache file through composer's class map, and a class recorded as untransformed from its original path, so both
   are cached by opcache; a `php://filter` include is never cached by opcache, which is why it is reserved for cache
   misses and debug mode.
3. The filter calls `SourceTransformingLoader::transformSource()`: a usable cache record serves the cached woven
   source without parsing anything; a miss runs the transformer chain (`SyntaxTreeRewriter` with its rules, then
   `WeavingTransformer`, then your own transformers) and persists the result.
4. `WeavingTransformer` converts the original class into the trait `FooOriginalTrait` (line numbers preserved, so
   breakpoints keep working) and generates the proxy class `Foo` into the cache directory: it re-inherits the parent
   and the interfaces, implements `Go\Aop\Proxy`, uses the trait with private `<method>OriginalAlias` aliases and
   dispatches every advised method through a per-method `static $__joinPoint`.

## Cache layout

Below `cacheDir`, mirroring the application tree relative to `appDir`: for `src/Foo.php` the woven source (the
trait) is cached as `src/FooOriginalTrait.php` and the proxy class next to it as `src/Foo.php` (included from the
woven source through `AOP_CACHE_DIR`), function proxies go to `_functions/`, plus the advisor cache and the index
files `_transformation.cache` (what was transformed, with size and mtime of the source) and `_include.cache` (the
include map). The freshness rule lives in `Go\Instrument\ClassLoading\CachePathManager`. The cache is built ahead of time by
`bin/aspect cache:warmup:aop` and trusted completely with `Features::PREBUILT_CACHE`; the kernel options, the
`Features` flags, the console commands and the deployment recipe are documented in [configuration.md](../configuration.md),
the generated code and the join points in [advices.md](../advices.md).

## Supported join points

Every join point of the framework: method execution (instance, static, private), function execution through
namespaced proxies, property access, introductions, `initialization(...)` and `staticinitialization(...)`, enum and
trait weaving, include interception with `Features::INTERCEPT_INCLUDES`.

## Trade-offs

- A cache miss costs a parse and the transformer chain; a warm production cache costs nothing beyond composer's class
  map.
- The woven class is a generated proxy: `get_class()` returns the original name, but the original body lives in a
  trait and reflection of the class points at the cache file (`__FILE__`/`__DIR__` are rewritten to the original
  paths).
- Includes are only intercepted when `Features::INTERCEPT_INCLUDES` rewrites them in woven files; code loaded by
  other means than composer and the rewritten includes is never woven. The [stream wrapper driver](stream-wrapper.md)
  removes that limit.
