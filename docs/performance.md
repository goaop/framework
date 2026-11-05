# Performance

Go! AOP weaves aspects into your source code once, stores the result as plain PHP files and from then on serves those
files like any other code: OPcache caches them, Composer loads them, and no parser, reflection or `eval()` is involved
at runtime. A production request on a warm cache reads one metadata file, hands the woven classes to Composer and pays
only for the calls that are actually intercepted. Methods and properties without advices cost nothing.

This document shows the numbers, explains the optimizations behind them and how to get the most out of them in a real
application: cache warm-up, OPcache tuning, Composer, Docker and Kubernetes.

- [At a glance](#at-a-glance)
- [What a request does in each mode](#what-a-request-does-in-each-mode)
- [Optimizations in the framework](#optimizations-in-the-framework)
- [Production checklist](#production-checklist)
- [Warming up the cache](#warming-up-the-cache)
- [Tuning OPcache](#tuning-opcache)
- [Composer autoloader](#composer-autoloader)
- [Docker and Kubernetes](#docker-and-kubernetes)
- [Writing aspects that stay fast](#writing-aspects-that-stay-fast)
- [How the numbers were measured](#how-the-numbers-were-measured)

## At a glance

Measured on a small shop application: about 250 files below the include paths (94 woven classes), 120 library classes
outside of them and 6 aspects with before, around, after-throwing and property advices. One request places 50 orders,
which makes ~2,600 intercepted method calls and ~950 intercepted property accesses. PHP 8.5.12, OPcache in shared
memory, JIT disabled. Details in [How the numbers were measured](#how-the-numbers-were-measured).

- **hot**: AOP cache present, OPcache filled. The steady state of a production worker.
- **warm**: AOP cache present, OPcache empty: a freshly started PHP-FPM or a new pod.
- **cold**: nothing cached yet. The first request weaves every class it loads.

| Mode                                   | hot         | warm  | Peak memory (hot) |
|----------------------------------------|------------:|------:|------------------:|
| `debug => false` + `PREBUILT_CACHE`    | **5.4 ms**  | 59 ms | 1.3 MB            |
| `debug => false`                       | **5.5 ms**  | 62 ms | 1.3 MB            |
| `debug => true`                        | 13.0 ms     | 63 ms | 2.2 MB            |
| Same application without AOP (aspects do not run) | 2.1 ms | 36 ms | 0.7 MB      |

- **~3,500 interceptions with real advice bodies (logging, cache lookups, timers) add ~3.3 ms** to the request.
  Booting the kernel takes ~0.15 ms of it.
- **One intercepted call costs about a third of a microsecond**, every further advice on it ~70 ns, see
  [Cost per call](#cost-per-call).
- **The whole cache is built in under half a second** with `cache:warmup:aop` (2,000 files: 1.1 s with 4 workers).
- **Containers start hot**: with a primed OPcache file cache the first request of a new PHP-FPM takes 19 ms instead of
  71 ms, and with preloading on top 10 ms, the same as the plain application without AOP (9 ms). See
  [Docker and Kubernetes](#docker-and-kubernetes).

### Cost per call

Empty advices, so only the framework's own work is measured: the joinpoint, the interceptor chain and the call of the
original method.

| Call                                        | Overhead per call |
|---------------------------------------------|------------------:|
| Method or property without advice in a woven class | **0**     |
| Method with one `#[Before]` or `#[Around]`  | ~0.33 µs          |
| Method with three `#[Around]`               | ~0.48 µs          |
| Static method with one `#[Around]`          | ~0.34 µs          |
| Property read with one `#[Around]`          | ~0.22 µs          |
| Property write with one `#[Around]`         | ~0.26 µs          |

A million intercepted calls cost about a third of a second, so a typical request with a few hundred interceptions pays a
fraction of a millisecond.

### CLI processes

Every CLI process starts with an empty OPcache. A primed `opcache.file_cache` makes short-lived commands start almost
as fast as a hot web request:

| Script time per process         | OPcache off | OPcache on (memory only) | `opcache.file_cache`, primed |
|---------------------------------|------------:|-------------------------:|-----------------------------:|
| `PREBUILT_CACHE`                | 33 ms       | 69 ms                    | **21 ms**                    |
| `debug => false`                | 30 ms       | 69 ms                    | **22 ms**                    |
| Without AOP                     | 19 ms       | 34 ms                    | 10 ms                        |

## What a request does in each mode

| Step                                          | `debug => false` (+ `PREBUILT_CACHE`)               | `debug => true` |
|-----------------------------------------------|-----------------------------------------------------|-----------------|
| Kernel `init()`                               | options, container, service factories (nothing is created) | the same, plus the kernel and aspect files tracked for changes |
| `_include.cache` (class map, skip set)        | one `include`, served from OPcache                  | the same        |
| Woven class                                   | Composer's class map → cached file, from OPcache    | freshness check, then the stream filter, so XDebug breakpoints and magic constants point at your original files |
| Untransformed class below the include paths   | skip set → the original file, from OPcache          | freshness check → the original file, from OPcache |
| Parser, transformers, stream filter           | never loaded                                        | stream filter registered, no transformer built |
| A changed source file                         | the cache built at deploy time is served as it is   | woven again on the next request |

File-system calls of one hot request, counted by the profiler:

| Call                | `PREBUILT_CACHE` | `debug => false` | `debug => true` | Without AOP |
|---------------------|-----------------:|-----------------:|----------------:|------------:|
| `filemtime` / `filesize` | **0**       | **0**            | 323 / 239       | 0           |
| `file_get_contents` | **0**            | **0**            | 94              | 0           |
| `file_exists` (Composer PSR-4) | 307   | 307              | 412             | 357         |

A production request checks no source file at all, and even needs fewer Composer lookups than the plain application,
because the woven classes come straight from the class map. An optimized Composer class map removes the remaining
lookups, see [Composer autoloader](#composer-autoloader).

## Optimizations in the framework

### Boot: nothing is built until it is needed

- **Deferred services.** The framework's services, the transformers and your aspects are registered as factories:
  one array write each. Nothing is autoloaded, reflected or created at boot, so a warm request never loads the parser,
  the pointcut grammar, the advice matcher or the transformers.
- **Aspects are created on the first call of one of their advices**, as PHP 8.4 native lazy objects. Weaving and the
  cache warm-up never create an aspect at all; they only read its class.
- **One eager service.** The cache index (`CachePathManager`) is the only service built in `init()`, because the
  autoloader reads the class map right away.
- **Zero-cost registration in production.** Debug mode tracks aspect files for changes through a registration
  listener that resolves the files through Composer without loading the classes; production arms no listener at all.
- **Precompiled pointcut grammar.** The parse table of the pointcut grammar is generated PHP, and pointcuts are parsed
  on first use only.

### Class loading: woven classes are ordinary classes

- **Composer serves woven classes.** In production the class map of the cache is handed to Composer with
  `addClassMap()`: a woven class resolves to its cached file natively, without `realpath()`, path filtering or a stream
  filter, and known untransformed classes return at once.
- **A minimal runtime index.** `_include.cache` holds only what a request needs (class map, skip set, include map). The
  full build records live in `_transformation.cache`, which production never reads.
- **Constant arrays instead of `unserialize()`.** Both metadata files are `var_export()`ed arrays with
  `__DIR__`-relative paths. OPcache keeps them immutable in shared memory, so including them copies nothing, and the
  cache stays valid when the application directory moves.
- **Trusted cache with `PREBUILT_CACHE`.** No source `filemtime` comparisons, no tracked-resource checks, no advisor
  cache freshness checks and no directory probes. It works on read-only file systems.
- **Opcache-friendly debug mode.** A fresh untransformed file is included by its original path, so OPcache caches it
  even in debug mode (~38 µs → ~10 µs per file, debug requests 27–40 % faster).
- **Native includes.** With `Features::INTERCEPT_INCLUDES`, an `include` of a file known to the cache is served by its
  original or cached path in production, without the stream filter (~250 µs → ~11 µs per include).
- **Atomic writes.** Every cache file is written to a unique temporary file and renamed into place, then invalidated in
  OPcache. Concurrent requests never see a half-written file, no locks are needed, and concurrent writers of the
  metadata merge their records instead of overwriting each other.

### Weaving: fast builds, done once

- **The cache hit path parses nothing.** A fresh record is served as the cached content before any transformer exists;
  the stream filter and the transformer chain are built on the first miss only.
- **One syntax tree walk for all rewriting rules.** Rules declare the node types they handle and, optionally, source
  markers: a rule whose marker does not occur in the file is skipped, and a file without any applicable rule is not
  walked at all, which is the case for most files.
- **Machinery switched on by your aspects.** `new` expressions are rewritten only while an `initialization(...)`
  pointcut is registered, function proxies only exist for function pointcuts, methods are matched only by method
  advisors, properties only by property advisors.
- **Unadvised classes skip the expensive checks.** The aspect check, which reflects every ancestor, runs only for
  classes that have advices.
- **Compiled advisor caches.** The advisors of an aspect are compiled into a plain PHP file of static constructor
  expressions, about twice as fast to load as `unserialize()`; its interceptors are lazy, so loading it creates no
  aspect and no closure.
- **Proxies built as syntax trees and printed once** (−43 % weaving time); the woven trait is produced by token edits
  that keep your line numbers for XDebug.
- **Parallel warm-up.** `cache:warmup:aop --workers=N` weaves in worker processes, largest files first; the
  coordinator writes the metadata once, and every process keeps a bounded number of syntax trees in memory.

### Runtime: a short, direct call path

- **One static joinpoint per member.** Every intercepted method declares
  `static $__joinPoint = InterceptorInjector::forMethod(...)`: the joinpoint and its interceptor list are built on its
  first call, every later call is a single `$__joinPoint->__invoke($this, [$args])`.
- **First-class callable advices.** The interceptor list holds `The::aspect(X::class)->method(...)` closures pointing
  straight at your aspect methods: no advisor lookup, no reflection, no `__call()`.
- **Hand-inlined `__invoke()`/`proceed()`.** Joinpoints keep their state in properties, push a stack frame only for
  recursive calls, merge variadic arguments only when there are any, and call the original method through one cached
  `ReflectionMethod`. Reflection for advices (`getMethod()`, `getField()`) is created only when an advice asks for it.
- **Native property hooks.** Property interception compiles to PHP 8.4 `get`/`set` hooks on the proxy: no
  `__get()`/`__set()` magic and no reflection per access.
- **Unadvised members are untouched.** The proxy overrides only the advised members; every other method and property
  is your original code, so it runs at full speed.

## Production checklist

1. `'debug' => false` and `'features' => Features::PREBUILT_CACHE`.
2. Build the cache at deploy or image build time with `cache:warmup:aop`.
3. Keep `includePaths` narrow: your `src/`, not `vendor/`.
4. Register aspects with `addLazyService()`.
5. `composer install --no-dev --classmap-authoritative`.
6. OPcache with `opcache.validate_timestamps=0` and enough memory and file slots for the woven files.
7. In containers: the cache inside the image, plus a primed `opcache.file_cache` or preloading.

## Warming up the cache

```bash
rm -rf var/cache/aop
vendor/bin/aspect cache:warmup:aop public/index.php --workers=$(nproc)
```

Start from an empty directory, so the cache contains exactly the current release. The argument is the file that initializes your kernel (the front controller or a bootstrap file). It is executed, so it
initializes the kernel with the same options as production. The command:

- weaves every file below `includePaths` (minus `excludePaths`) and writes the woven files, the proxies, the advisor
  caches and both metadata files;
- reports every file that can not be woven on stderr and exits with a non-zero code, so a deployment script or a
  `docker build` stops right there;
- `--workers=N` weaves in N processes. Use the number of CPUs: a 2,047-file application took 2.2 s with one worker and
  1.1 s with 4 workers on 4 CPUs;
- `--cached-files=N` (default 100) bounds the syntax trees kept in memory per process;
- `--cache-dir=DIR` builds the cache in another directory from scratch while the kernel keeps serving from its own: a
  release is prepared next to the running one and switched in atomically, e.g. with a symlink swap.
- run over an existing directory, the command weaves again every file whose source changed (size or modification
  time) and keeps the others, also with `PREBUILT_CACHE`: only requests trust the cache as it is.

Rebuild the cache whenever the code, the aspects, the kernel options or `composer.lock` change; in practice, on every
deployment. Debug mode needs no rebuilds: it re-weaves changed classes, aspects and kernel files by itself.

## Tuning OPcache

Every woven class becomes two cached files (its original body as a trait, and the proxy), and every aspect gets an
advisor cache file. Give OPcache room for them:

| Setting                            | Production value | Why |
|------------------------------------|------------------|-----|
| `opcache.enable`                   | `1`              | Woven code is plain PHP, cached like the rest of the application. |
| `opcache.memory_consumption`       | `128`–`256` (MB) | Check `opcache_get_status()['memory_usage']` under load. |
| `opcache.interned_strings_buffer`  | `16`–`32` (MB)   | Proxies and woven traits share class and method names. |
| `opcache.max_accelerated_files`    | ≥ 2 × the PHP files of the application and `vendor/` | Every woven class adds one file. |
| `opcache.validate_timestamps`      | `0`              | Immutable releases and containers: no `stat` per include. Reload PHP-FPM after a deployment. |
| `opcache.file_update_protection`   | `0`              | The framework writes every cache file atomically, so OPcache can cache freshly woven files immediately instead of waiting 2 seconds (the default). |
| `opcache.file_cache`               | a directory      | A second-level cache on disk: a new PHP-FPM master or CLI process loads compiled scripts instead of compiling them. Prime it at build time. |
| `opcache.preload`                  | optional         | Links the woven classes once at server start, see [Preloading](#preloading). |
| `realpath_cache_size` / `realpath_cache_ttl` | `4096K` / `600` | With `validate_timestamps=1`: fewer path lookups per include. |

Debug mode is made for development: woven classes are served through the stream filter so that XDebug breakpoints and
magic constants point at your original files, and changed files are re-woven automatically. Its hot requests take
~13 ms in the benchmark, production ~5.5 ms.

### JIT

Woven code is ordinary PHP, compiled by the JIT like the rest of the application. A typical web request spends its time in many short calls, where
the JIT gains little, so measure before enabling it. On PHP 8.5.11 and 8.5.12 the tracing JIT (`opcache.jit=tracing`,
also the default of `opcache.jit=on`) has a bug of its own: use `opcache.jit=disable` with these versions.

### Preloading

Woven files are ordinary class declarations, so a prebuilt cache can be preloaded. This script links every woven class,
its proxy and the untransformed application classes into OPcache at server start:

```php
<?php
// config/preload.php — opcache.preload=/app/config/preload.php, opcache.preload_user=www-data
$appDir   = dirname(__DIR__);
$cacheDir = $appDir . '/var/cache/aop';

$loader = require $appDir . '/vendor/autoload.php';
define('AOP_CACHE_DIR', $cacheDir);                 // woven files include their proxies through it

$includeMap = include $cacheDir . '/_include.cache';
$loader->addClassMap($includeMap['map']);           // woven classes resolve to the cache, like in production

foreach ([...array_keys($includeMap['map']), ...array_keys($includeMap['skip'])] as $class) {
    class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
}
```

The kernel is initialized on every request as usual; the autoloader simply never has to load the preloaded classes.
In the benchmark, preloading cut the included files per request from 517 to 174, the hot request from 5.2 ms to
4.9 ms and the first request after a server start from 71 ms to 24 ms (10 ms together with a primed file cache).
Preloaded classes change with a restart of PHP-FPM, which is how containers are deployed anyway. The script reads the
internal format of `_include.cache`, so keep it in step with the framework version you deploy.

## Composer autoloader

Classes that are not woven are found by Composer as usual. With an optimized class map every lookup is an array access
instead of a `file_exists()`, and the woven class map is merged into it, so woven and untransformed classes are served
the same way. Hot request of the benchmark:

| Autoloader                         | `PREBUILT_CACHE` | `debug => false` | Without AOP |
|------------------------------------|-----------------:|-----------------:|------------:|
| PSR-4 (`composer install`)         | 5.5 ms           | 6.7 ms           | 2.3 ms      |
| `--optimize-autoloader` (`-o`)     | 5.2 ms           | 5.0 ms           | 1.6 ms      |
| `--classmap-authoritative` (`-a`)  | **4.8 ms**       | **4.6 ms**       | 1.9 ms      |

With `--classmap-authoritative` Composer never looks for a class outside the class map: run `dump-autoload` after any
code generation and before the warm-up.

## Docker and Kubernetes

Build the AOP cache into the image. Every container then starts with a complete, read-only cache, every replica serves
exactly the same woven code, and no pod ever weaves. The [shop demo](https://github.com/goaop/shop-demo) ships this
setup as a working image.

```dockerfile
FROM php:8.5-fpm

# OPcache is part of PHP 8.5 (PHP 8.4 images: RUN docker-php-ext-install opcache)
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

# 1. The AOP cache: woven classes, proxies, advisor caches, metadata, owned by the user that serves it
#    (cache files get the `cacheFileMode` kernel option, by default 0770 minus the umask)
RUN rm -rf var/cache/aop \
 && php vendor/bin/aspect cache:warmup:aop public/index.php --workers="$(nproc)" \
 && chown -R www-data:www-data var/cache/aop \
 && chmod -R a-w var/cache/aop

# 2. The OPcache file cache: a new PHP-FPM master loads compiled scripts instead of compiling them.
#    Built in the final image, as the file cache belongs to this PHP build and these paths.
RUN mkdir -p /var/cache/opcache \
 && php -d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache_read_only=0 \
        docker/opcache-prime.php /app /app/var/cache/aop /app/public/index.php \
 && chown -R www-data /var/cache/opcache

USER www-data
```

`docker/opcache-prime.php` compiles what the application loads: Composer's class map, Composer's autoloaded files,
the AOP cache and the entry points:

```php
<?php
// Usage: php opcache-prime.php <app dir> <AOP cache dir> [entry point files...]
[, $appDir, $aopCacheDir] = $argv;
$files = [
    ...array_values(require "$appDir/vendor/composer/autoload_classmap.php"),
    ...(is_file("$appDir/vendor/composer/autoload_files.php") ? array_values(require "$appDir/vendor/composer/autoload_files.php") : []),
    ...array_slice($argv, 3),
];
$cacheFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($aopCacheDir, FilesystemIterator::SKIP_DOTS));
foreach ($cacheFiles as $file) {
    if ($file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
$compiled = 0;
foreach (array_unique($files) as $file) {
    $compiled += (int) opcache_compile_file($file);
}
echo "Compiled $compiled files into opcache.file_cache\n";
```

`docker/opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=50000
opcache.validate_timestamps=0
opcache.file_cache=/var/cache/opcache
opcache.file_cache_consistency_checks=0
; PHP 8.5: the primed file cache is only read at runtime, also on a read-only root file system
opcache.file_cache_read_only=1
; required on PHP 8.5.11 / 8.5.12, see "JIT"
opcache.jit=disable
; optional, see "Preloading"
; opcache.preload=/app/config/preload.php
; opcache.preload_user=www-data
```

The kernel of the image:

```php
ApplicationAspectKernel::getInstance()->init([
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
    'features'     => Features::PREBUILT_CACHE,
]);
```

In the benchmark this setup serves the first request of a fresh server in 19 ms with the file cache and in 10 ms with
the file cache plus preloading, against 71 ms without either: pods are fast from their first request.

Running on Kubernetes or another orchestrator:

- **Read-only root file system** (`readOnlyRootFilesystem: true`): `PREBUILT_CACHE` reads the cache built into the
  image and writes nothing at runtime.
- **One image, one cache.** Keep the cache inside the image rather than on a shared volume, so every release serves
  exactly the code it was built from.
- **Rolling updates** need nothing special: new pods bring their own cache, old pods keep theirs.
- **When the image can not contain the cache**, run `cache:warmup:aop` in an init container that writes to an
  `emptyDir` volume mounted at the cache path of the application container, which keeps `PREBUILT_CACHE`. The pod
  becomes ready with a complete cache.
- **CLI containers** (queue consumers, cron jobs) use the same image and cache; enable `opcache.enable_cli=1` together
  with the primed `opcache.file_cache`, see [CLI processes](#cli-processes).
- **Long-running workers** (RoadRunner, FrankenPHP worker mode, Swoole, ReactPHP) boot the kernel once per worker, and
  the joinpoints stay initialized from one request to the next.

## Writing aspects that stay fast

- **Target precisely.** Only matched members are intercepted and only matched classes are woven, so a precise pointcut
  is a fast one. `execution(public App\Service\*->*(*))` also matches constructors; add
  `&& !execution(**->__construct(*))` when you do not need them.
- **Prefer method pointcuts to `initialization(...)`.** While an `initialization` pointcut is registered, every `new`
  in the woven files goes through an interceptor.
- **Register aspects lazily** with `addLazyService()`: the aspect and its dependencies are created on the first advice
  call, not on every request.
- **Keep advices cheap.** `getMethod()`/`getField()` create a reflection object on first use per joinpoint; derive
  attributes or names from it once and keep them in the aspect, keyed by `class::method`.
- **Format joinpoints lazily.** `(string) $invocation` builds a string on every call; use it for messages that are
  actually written.

## How the numbers were measured

- Application: ~45 hand-written classes (domain, repositories, services, controllers, in-memory infrastructure),
  40 generated modules (service and repository woven; DTO, validator and event not woven) and 120 library classes
  outside the include paths. Aspects: logging (`#[Before]` on an attribute), caching and transactions (`#[Around]` on
  attributes), monitoring (`#[Around]` on repositories, `#[AfterThrowing]` on services), stock audit (`#[Around]` on a
  property), security (`#[Before]` on module services). The same code without the kernel is the baseline: its aspects
  do not run.
- PHP 8.5.12 NTS, 4 CPUs, OPcache with `opcache.jit=disable` and `opcache.file_update_protection=0`.
- Web requests: the built-in web server (`php -S`, one worker, OPcache shared memory kept between requests). Warm is
  the first request of a freshly started server (7 samples), hot the median of 350 requests. Times are measured inside
  the script with `hrtime()`.
- Profiles: Xdebug 3.5 `xdebug.mode=profile` runs of every mode and state, used for call counts and the distribution of
  the time; the times above come from runs without Xdebug.
- The ratios between the modes are what carries over to other machines.
- The application, the benchmark scripts and the Docker image are in [goaop/shop-demo](https://github.com/goaop/shop-demo);
  `bash bench/run-all.sh` reproduces every table of this document.
