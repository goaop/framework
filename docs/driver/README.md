# Weaving drivers

A weaving driver decides *how* the classes of an application get their advices woven in. Everything else is shared:
aspects, pointcuts, the advice matcher, the advisor cache and the join-point runtime. The `driver` kernel option names
the class of the driver (see [configuration](../configuration.md)):

```php
ApplicationAspectKernel::getInstance()->init([
    'driver'       => Go\Driver\StreamFilter\StreamFilterDriver::class, // the default
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

| Driver | Package | Weaves by |
|--------|---------|-----------|
| [`Go\Driver\StreamFilter\StreamFilterDriver`](stream-filter.md) (default) | `goaop/framework` (built in) | Source transformation of advised files at load time through a `php://filter` stream filter behind composer's loader. Pure PHP. |
| [`Go\Driver\StreamWrapper\StreamWrapperDriver`](stream-wrapper.md) | `goaop/stream-wrapper-driver` | Source transformation of every included file within the weaving scope through a `file://` stream wrapper; includes are always intercepted, opcache caches the woven sources. Pure PHP. |
| [`Go\Driver\Ffi\FfiDriver`](ffi.md) (experimental) | `goaop/ffi-driver` | Runtime rewiring of the methods of a loaded class through [lisachenko/z-engine](https://github.com/lisachenko/z-engine) (PHP FFI): no source rewriting, method execution join points only. |

An option value that is not an existing class, or a class that does not implement `Go\Driver\WeavingDriver`, throws
`Go\Aop\Exception\InvalidConfigurationException` from `init()`.

## The interface

```php
namespace Go\Driver;

interface WeavingDriver
{
    public function boot(AspectKernel $kernel, AspectContainer $container): void;
}

interface SourceTransformingDriver extends WeavingDriver {}
```

`AspectKernel::init()` instantiates the configured class without arguments, registers the instance in the container
under `Go\Driver\WeavingDriver` and calls `boot()` once: the kernel options are normalized, the framework services are
registered, no aspect is loaded yet and `configureAop()` runs right after. In `boot()` the driver registers the
services it needs and takes over class loading, e.g. by wrapping composer's loader (implement
`Go\Instrument\ClassLoading\ComposerLoaderDecorator`, so tools unwrap it and a later kernel wraps the original loader
again), registering a stream filter or wrapper, or hooking the engine.

A driver that weaves by running sources through the transformer chain
(`AspectKernel::registerTransformerServices()`) implements the marker `SourceTransformingDriver`:

- the kernel registers the transformer services for such a driver only,
- the one entry point `Go\Instrument\ClassLoading\SourceTransformingLoader::transformSource($uri, $source, $stream)`
  returns the source the way the application must see it (cache hit, transformer chain on a miss, untouched source
  without a usable cache directory),
- the cache warmer (`cache:warmup:aop`, `debug:weaving`) can build its cache ahead of time; for any other driver it
  throws `InvalidConfigurationException` (the warmer requires a source-transforming driver).

## Writing a driver

1. Create a package with a `final class ... implements WeavingDriver` (or `SourceTransformingDriver` when the
   transformer chain does the weaving) and a parameterless constructor.
2. In `boot()`, read the normalized options from `$kernel->getOptions()`, refuse what the driver cannot honour with an
   `InvalidConfigurationException` (the ffi driver refuses `Features::INTERCEPT_INCLUDES`), register the driver's
   services with `$container->addLazyService()` and hook into class loading.
3. Keep the driver's own cache files below `cacheDir`, separate from the stream filter driver's
   `_transformation.cache` (its records mean something to that driver), and decide freshness with
   `CachePathManager::isFreshRecord()`, the one shared rule.
4. Mark woven classes with `Go\Aop\Proxy` where that is possible, so `instanceof Proxy` answers alike under every
   driver.

The two drivers in `packages/` of the framework repository are the reference implementations.
