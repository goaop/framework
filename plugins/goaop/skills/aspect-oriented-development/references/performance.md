# Performance

Performance is the question users ask most about AOP. Answer it with measured numbers and their conditions, not
with adjectives, and never invent a figure. The numbers below come from the benchmark of the framework
(`docs/performance.md`, reproducible with [goaop/shop-demo](https://github.com/goaop/shop-demo)); the ratios carry
over to other machines, the absolute values do not. For a decision that depends on it, measure the user's own
application.

## Contents

- [What costs what](#what-costs-what)
- [Measured numbers](#measured-numbers)
- [Production checklist](#production-checklist)
- [Why production mode is fast](#why-production-mode-is-fast)
- [Building the cache](#building-the-cache)
- [OPcache](#opcache)
- [Containers](#containers)
- [Writing aspects that stay fast](#writing-aspects-that-stay-fast)
- [Diagnosing a slow application](#diagnosing-a-slow-application)

## What costs what

| Cost | When it is paid | Size |
|------|-----------------|------|
| Weaving (parsing, matching, code generation) | Once per class, when the cache is built | The whole cache of the benchmark application in under half a second |
| Kernel boot | Every request | ~0.15 ms in production mode |
| Loading woven classes | Every request, from OPcache | Two files per woven class instead of one |
| A method or property without advice, also in a woven class | Never | 0 |
| An intercepted call | Every call | ~0.33 µs plus the advice body |
| Creating an aspect | Once per request, on the first call of one of its advices | The constructor and its dependencies |
| Debug mode | Every request, in development | Freshness checks and the stream filter: roughly twice the production time in the benchmark |

The framework's own overhead is small and fixed. What usually decides the cost of an aspect is the advice body: a
log write, a cache lookup or a Redis call per intercepted call is thousands of times more expensive than the
interception.

## Measured numbers

Conditions: a small shop application, about 250 files below the include paths (94 woven classes), 6 aspects. One
request places 50 orders: ~2,600 intercepted method calls and ~950 intercepted property accesses. PHP 8.5.12,
OPcache in shared memory, JIT off, 4 CPUs.

Request time. "Hot" is the steady state of a worker, "warm" the first request of a freshly started PHP-FPM with an
AOP cache but an empty OPcache:

| Mode                                   | hot     | warm  | Peak memory (hot) |
|----------------------------------------|--------:|------:|------------------:|
| `debug => false` + `PREBUILT_CACHE`    | 5.4 ms  | 59 ms | 1.3 MB            |
| `debug => false`                       | 5.5 ms  | 62 ms | 1.3 MB            |
| `debug => true`                        | 13.0 ms | 63 ms | 2.2 MB            |
| Same application without AOP (the aspects do not run) | 2.1 ms | 36 ms | 0.7 MB |

The ~3.3 ms between the production modes and the baseline are ~3,500 interceptions *with real advice bodies*
(logging, cache lookups, timers). The baseline does not do that work at all, so the difference is the price of the
features, not of the framework.

Framework overhead per call, with empty advices:

| Call                                               | Overhead per call |
|----------------------------------------------------|------------------:|
| Method or property without advice in a woven class | 0                 |
| Method with one `#[Before]` or `#[Around]`         | ~0.33 µs          |
| Method with three `#[Around]`                      | ~0.48 µs          |
| Static method with one `#[Around]`                 | ~0.34 µs          |
| Property read with one `#[Around]`                 | ~0.22 µs          |
| Property write with one `#[Around]`                | ~0.26 µs          |

A million intercepted calls cost about a third of a second; a request with a few hundred interceptions pays a
fraction of a millisecond. Every further advice on the same joinpoint adds about 70 ns.

Other figures from the same benchmark:

- Cache build: under half a second for the application; 2,047 files take 2.2 s with one worker and 1.1 s with 4.
- Optimized Composer autoloader: 5.5 ms → 4.8 ms per hot request with `--classmap-authoritative`.
- First request of a new PHP-FPM: 71 ms, 19 ms with a primed OPcache file cache, 10 ms with preloading on top (the
  plain application: 9 ms).
- Short CLI processes with a primed `opcache.file_cache`: 21 ms instead of 69 ms.
- A native `new` in a woven file while an `initialization(...)` pointcut is registered: about 130 ns more than a
  plain `new`, for classes without initialization advice.

Framework bridges add their own boot: the Yii3 bridge measures +0.31 ms per request in production with a hot
OPcache and no aspects, and about 10 µs for resolving an aspect from the container on its first advice call
(`benchmarks/RESULTS.md` of that bridge). The shop demos of the other bridges contain their benchmarks.

## Production checklist

1. Production mode: `'debug' => false` and `'features' => Features::PREBUILT_CACHE` (with a bridge: its `debug` and
   `features` settings).
2. Build the cache at deploy or image build time, from an empty directory, on every deployment.
3. Keep `includePaths` narrow: the application sources, not `vendor/`.
4. Register aspects lazily (`addLazyService()`; the bridges do).
5. `composer install --no-dev --classmap-authoritative`. Run `dump-autoload` after any code generation and before
   the warm-up.
6. OPcache with `opcache.validate_timestamps=0` and room for the woven files.
7. In containers: the cache inside the image, plus a primed `opcache.file_cache` or preloading.

## Why production mode is fast

Useful when a user asks what the framework does at runtime:

- **No parser, reflection or `eval()` at runtime.** A production request reads one metadata file from OPcache and
  hands the class map of the cache to Composer. Woven classes are then ordinary files that Composer loads and
  OPcache caches. The weaver, the pointcut parser and the transformers are never loaded.
- **No file system checks.** A production request does no `filemtime()` or `file_get_contents()` on sources (debug
  mode: 323 and 94 in the benchmark). With `PREBUILT_CACHE` nothing is probed or written, so read-only file systems
  work.
- **One joinpoint per member.** An intercepted method holds `static $__joinPoint`, built on its first call; later
  calls are one `$__joinPoint->__invoke($this, [$args])`.
- **Advices are first-class callables** on the aspect: no lookup by name, no `__call()`, no reflection per call.
- **Property interception is native property hooks**, not `__get()`/`__set()`.
- **Aspects are lazy.** An aspect and its dependencies are created on the first call of one of its advices, never at
  boot and never while weaving.
- **Unadvised members are untouched**: the generated class re-declares only advised members.

## Building the cache

```bash
rm -rf var/cache/aop
```

```bash
vendor/bin/aspect cache:warmup:aop public/index.php --workers=$(nproc)
```

With a bridge use its command (`frameworks.md`). The command weaves every file below the include paths, exits
non-zero when one cannot be woven, and takes:

| Option | Purpose |
|--------|---------|
| `--workers=N` | Weave in N processes; use the number of CPUs |
| `--cache-dir=DIR` | Build into another directory while the running release keeps its own, for an atomic switch |
| `--preload[=FILE]` | Also write the `opcache.preload` script of the cache (`_preload.php` in the cache directory) |
| `--cached-files=N` | Parsed files kept in memory per process (default 100): lower it when the build runs out of memory |
| `--fail-fast` | Stop at the first file that cannot be woven |

Rebuild whenever the code, the aspects, the kernel options or `composer.lock` change: in practice on every
deployment.

## OPcache

Every woven class is two cached files (the original body as a trait, and the generated class), and every aspect has
an advisor cache file.

| Setting | Production value | Why |
|---------|------------------|-----|
| `opcache.enable` | `1` | Woven code is plain PHP |
| `opcache.memory_consumption` | `128`–`256` | Check `opcache_get_status()['memory_usage']` under load |
| `opcache.interned_strings_buffer` | `16`–`32` | Generated classes and traits share names |
| `opcache.max_accelerated_files` | at least twice the PHP files of the application and `vendor/` | One more file per woven class |
| `opcache.validate_timestamps` | `0` | No `stat` per include; reload PHP-FPM after a deployment |
| `opcache.file_update_protection` | `0` | Cache files are written atomically, so OPcache may cache fresh ones at once |
| `opcache.file_cache` | a directory, primed at build time | New PHP-FPM masters and CLI processes load compiled scripts |
| `opcache.preload` | optional: the `_preload.php` of the cache, with `opcache.preload_user` | Links woven classes once at server start |

- **JIT**: woven code is compiled like any other code. Web requests gain little from it; measure first. On PHP
  8.5.11 and 8.5.12 the tracing JIT has a bug of its own that breaks pointcut parsing or crashes the process: use
  `opcache.jit=disable` there.
- **Preloading**: build with `--preload`, point `opcache.preload` at the script. A preloaded class cannot be woven
  afterwards, so never preload the original files of classes below the include paths. Rebuild the script with the
  cache and restart PHP-FPM after a deployment.
- **OPcache file cache**: `cache:warmup:opcache` compiles the AOP cache and the application into
  `opcache.file_cache`. Run it after `cache:warmup:aop`, in the final image, in a process started with
  `-d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache=DIR`; the command prints that
  command line when the settings are missing.

## Containers

Build the cache into the image, so that every container starts with a complete, read-only cache and no pod ever
weaves:

```dockerfile
RUN composer dump-autoload --no-dev --classmap-authoritative
RUN rm -rf var/cache/aop \
 && php vendor/bin/aspect cache:warmup:aop public/index.php --workers="$(nproc)" --preload \
 && chown -R www-data:www-data var/cache/aop \
 && chmod -R a-w var/cache/aop
```

- Keep the cache inside the image, not on a shared or persistent volume: a volume serves the cache of an older
  release to new containers.
- `readOnlyRootFilesystem: true` works with `PREBUILT_CACHE`.
- When the image cannot contain the cache, build it in an init container into an `emptyDir` mounted at the cache
  path.
- CLI containers (queue consumers, cron) use the same image; enable `opcache.enable_cli=1` with the primed file
  cache.
- The complete Dockerfile and `opcache.ini` are in `docs/performance.md`, "Docker and Kubernetes", and in the shop
  demos.

## Writing aspects that stay fast

- **Target precisely.** Only matched members are intercepted and only matched classes are woven. A pointcut such as
  `execution(public App\**->*(*))` intercepts every getter and constructor; advise the service boundary.
- **Exclude constructors** from broad pointcuts (`&& !execution(public **->__construct(*))`) unless they are meant.
- **Do not advise methods called in tight loops**, or keep that advice trivial. Ten thousand interceptions cost a
  few milliseconds of framework overhead, and the advice body on top.
- **Prefer method pointcuts to `initialization(...)`**: while one is registered, every `new` in woven files is
  rewritten.
- **Read attributes once.** `getMethod()` creates a reflection object on first use per joinpoint; instantiating an
  attribute on every call is the expensive part. Keep the settings in the aspect, keyed by `class::method`:

  ```php
  $settings = $this->settings[$method->class . '::' . $method->name] ??= $method->getAttributes(Cacheable::class)[0]->newInstance();
  ```

- **Format joinpoints lazily.** `(string) $invocation` builds a string on every call. Use `getScope()` and
  `getMethod()->name` for labels and keys, and the string form only for messages that are actually written.
- **Keep I/O out of hot advices**, or batch it: buffer metrics and log records and flush them at the end of the
  request.
- **Aspect constructors stay cheap**: they run on the first advice call of each request. Inject heavy services
  lazily.

## Diagnosing a slow application

1. **Which mode is running?** Debug mode in production is the most common cause: it checks files and serves woven
   classes through a stream filter that OPcache cannot cache.
2. **Is the cache prebuilt?** Without a warm-up the first requests after a deployment weave the classes they load,
   which takes tens of milliseconds.
3. **Is OPcache on, and large enough?** A full OPcache (`opcache_get_status()`: `cache_full`, `num_cached_keys`
   near `max_cached_keys`) silently recompiles files on every request.
4. **How many interceptions does a request make?** Count them with a temporary counter in the advice, or profile.
   Thousands per request point to a pointcut that is too broad.
5. **What does the advice body do?** Profile the aspect methods like any other code: the time is almost always in
   the advice, not in the interception.
6. **Is `includePaths` too wide?** Weaving `vendor/` multiplies build time and the number of cached files.
