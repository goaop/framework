# Configuration and deployment

## Kernel options

Pass the options to `init()` of your aspect kernel, usually in the front controller:

```php
ApplicationAspectKernel::getInstance()->init([
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

Only the options below are accepted. An unknown key, an unknown `containerClass` or an unknown feature
bit throws `Go\Aop\Exception\InvalidConfigurationException`.

| Option           | Type       | Default                     | Purpose |
|------------------|------------|-----------------------------|---------|
| `cacheDir`       | `string`   | none, required              | Directory for woven sources, proxies, advisor caches and the class map. |
| `appDir`         | `string`   | the directory that contains `vendor/` | Application root. Cache paths mirror source paths relative to it, and only files below it are woven. |
| `debug`          | `bool`     | `false`                     | Development mode: changes of the kernel file and of aspect files invalidate the cache, and every class and `include` is loaded through the weaving stream filter. With `false`, woven classes are loaded from the cache through Composer's class map. Use `false` in production. |
| `includePaths`   | `string[]` | `[]` (everything below `appDir`) | Directories that are woven. Keep it narrow: weaving vendor code is slow and rarely needed. |
| `excludePaths`   | `string[]` | `[]`                        | Directories that are never woven. The cache directory, the framework itself and its runtime dependencies (goaop/dissect, goaop/parser-reflection, nikic/php-parser, symfony/finder) are always excluded. |
| `features`       | `int`      | `0`                         | Bitmask of `Go\Aop\Features` flags, see below. |
| `cacheFileMode`  | `int`      | `0770` minus the umask      | Permissions of cache files. Must be between `0600` and `0777` and give the owner read and write access; directories get matching search bits. |
| `containerClass` | `class-string` | `Go\Core\Container`     | Aspect container implementation, must implement `Go\Core\AspectContainer`. |

## Features

Combine the flags with `|`, e.g. `'features' => Features::INTERCEPT_FUNCTIONS | Features::PREBUILT_CACHE`.

| Flag                        | Effect |
|-----------------------------|--------|
| `INTERCEPT_FUNCTIONS`       | Enables `execution(Namespace\function(*))` pointcuts for system functions called from your namespaced code. Expensive: every woven file is scanned for function calls. |
| `INTERCEPT_INITIALIZATIONS` | Enables `initialization(...)` pointcuts by rewriting `new Foo(...)` expressions in woven files. |
| `INTERCEPT_INCLUDES`        | Routes `include`/`require` in woven files through the weaver, for legacy code that is not loaded by Composer. |
| `PREBUILT_CACHE`            | Trusts the cache built at deploy time completely. Skips the cache directory and writability probes, source `filemtime` comparisons, tracked-resource checks and advisor cache freshness checks, and never writes to the cache directory. Rebuilding the cache on every deploy is your responsibility. Works on read-only file systems. |

## Console

`bin/aspect` needs `symfony/console` (`^7.4 || ^8.0`). Every command takes the path to the file that initializes your kernel, usually the front controller. That file is executed.

| Command                                   | Purpose |
|-------------------------------------------|---------|
| `cache:warmup:aop <loader> [--fail-fast]` | Weaves every file below the include paths into the cache. Fails when a file cannot be woven and writes the errors to stderr. |
| `debug:aspect <loader> [--aspect=...]`    | Lists the registered aspects and their advices. |
| `debug:advisor <loader> [--advisor=...]`  | Lists the advisors; with `--advisor` shows the joinpoints that advisor matches. |
| `debug:weaving <loader>`                  | Warms up the cache twice and reports proxies that are woven differently, which points to non-deterministic aspects. |

## Deployment

1. Clear the cache directory.
2. Warm up the cache: `vendor/bin/aspect cache:warmup:aop public/index.php`. The command fails when a file cannot be woven, so a deploy script stops instead of shipping an incomplete cache.
3. Run with `'debug' => false` and `Features::PREBUILT_CACHE`.

Rebuild the cache whenever the code or the aspects change. When upgrading from 3.x, see the [upgrade guide](../UPGRADE-4.0.md).
