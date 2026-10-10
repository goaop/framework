# The zengine weaving driver

The framework weaves advised classes with one of two drivers, selected by the `driver` kernel option:

| Driver              | How advised classes are rewired                                                                                                                                                                                               |
|---------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `stream` (default)  | Source transformation at load time: a composer autoloader wrapper redirects advised files through the `php://filter` stream filter, which converts the class to a trait and generates a proxy class into the cache directory. Pure PHP. |
| `zengine`           | Runtime method-table weaving through [lisachenko/z-engine](https://github.com/lisachenko/z-engine) (PHP FFI): classes load natively and, right after a class is loaded, the body of every advised method is swapped in place with a generated dispatcher, while the original body stays callable under a private alias. No source rewriting, no stream filter, no include interception. |

Everything above the driver is shared: aspects, pointcuts, the advisor cache, the interceptor chain and the
join-point API. An aspect written for one driver runs unchanged on the other, within the limits listed below.

## Requirements

- `ext-ffi` with `ffi.enable=1` (or `preload` with a preload script booting z-engine).
- `lisachenko/z-engine`, the branch matching your PHP minor (`8.4` for PHP 8.4, `8.5` for PHP 8.5). The
  package is an optional dependency of the framework, because every branch of it targets one PHP minor:

  ```bash
  composer config repositories.zengine vcs https://github.com/lisachenko/z-engine
  composer require lisachenko/z-engine:dev-feature/redefine-preserve-alias-8.4   # PHP 8.4
  composer require lisachenko/z-engine:8.5.x-dev                                 # PHP 8.5
  ```

  The `redefine(..., preserveAs:)` API the driver is built against is merged into `8.5`; until it reaches the
  `8.4` branch as well, the PHP 8.4 install pins the feature branch above.
- The opcache JIT **off** for the whole process: `opcache.jit=off` and `opcache.jit_buffer_size=0` in `php.ini`, the
  FPM pool or `php -d`. z-engine hooks the executor internals the JIT bypasses. On PHP 8.5 the tracing JIT also
  miscompiles code the framework runs (a known PHP bug, see `docs/php85-limitations.md`), so there the driver switches
  the JIT off at runtime as a safety net and records that in its boot report; the process-level setting stays the
  requirement, since code compiled before the kernel ran keeps its JIT state.

A kernel configured for the `zengine` driver without z-engine, without FFI or with the JIT on throws
`Go\Aop\Exception\InvalidConfigurationException` from `init()` with the missing requirement in the message.

## Configuration

```php
ApplicationAspectKernel::getInstance()->init([
    'driver'       => 'zengine',           // or Go\Aop\WeavingDriver::ZEngine
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

`includePaths`, `excludePaths`, `debug`, `cacheDir` and `cacheFileMode` mean the same as with the stream driver.
The `features` option must not contain `INTERCEPT_FUNCTIONS`, `INTERCEPT_INITIALIZATIONS`, `INTERCEPT_INCLUDES` or
`PREBUILT_CACHE`: those flags configure source transformers, and the engine driver never rewrites sources.

## How a class is woven

1. The kernel wraps composer's class loader with `Go\Instrument\ClassLoading\ZEngineComposerLoader`, which includes
   the original file exactly as composer would (opcache caches it, magic constants and line numbers are untouched).
2. Right after the include, `Go\Instrument\ZEngine\ZEngineClassWeaver` matches the advisors against the class (from
   the parsed source, so attributes, parameter defaults and the file's imports are seen as written) and generates
   the **donor class** `Ns\Foo__AopDonor` next to the class in its namespace: an abstract class, never instantiated,
   extending the same parent and implementing the same interfaces, that declares exactly the advised methods with
   the dispatcher bodies a proxy class would get (`static $__joinPoint = InterceptorInjector::forMethod(...)`).
   The donor file is written below `{cacheDir}/_zengine/`, mirroring the application tree, and recorded in the index
   `{cacheDir}/_zengine.cache` together with the advised method names.
3. `Go\Instrument\ZEngine\MethodTableRewriter` hands the class and its donor to z-engine:
   - a method the class declares itself keeps its body under the private alias `<method>OriginalAlias` and gets the
     dispatcher body swapped in place (`ReflectionMethod::redefine($donorMethod, preserveAs: ...)`); the dispatcher
     calls `$this-><method>OriginalAlias(...)` like a trait-based proxy;
   - a method the class only inherits gets an own override adopting the dispatcher body
     (`ReflectionClass::addMethod($method, $donorMethod)`), whose dispatcher reaches the original through
     `parent::<method>(...)`.

A warm request finds a fresh record in the index (same freshness rule as the stream driver: source size and mtime,
tracked resources in debug mode), loads the donor file and rewires the class without loading the aspects or running
the matcher.

Classes declared before the kernel was initialized (required directly, never autoloaded) are not seen by the loader.
Weave them on demand:

```php
$weaver = $kernel->getContainer()->getService(Go\Instrument\ZEngine\ZEngineClassWeaver::class);
$result = $weaver->weave(App\Legacy::class);   // Go\Instrument\ZEngine\WeaveResult: outcome and rewired methods
```

Weave before instances exist and before subclasses link: the engine mutation reaches neither objects created earlier
(under opcache) nor, for a class published in opcache shared memory, subclasses that were linked against the shared
entry (z-engine refuses that mutation, the driver reports it as `Go\Aop\Exception\UnsupportedJoinpointException`).

## Supported join points

The engine driver rewires **method bodies**: `execution(...)` pointcuts on instance and static methods, public,
protected and private, declared or inherited, in regular classes (final, abstract and readonly classes included).

Everything else only the stream driver provides, since it needs rewritten sources:

- property access (`access(...)` pointcuts),
- introductions (interfaces and traits),
- `initialization(...)` and `staticinitialization(...)` pointcuts,
- function interception (`Features::INTERCEPT_FUNCTIONS`), include interception, prebuilt caches,
- traits, enums and interfaces as weaving targets.

An advisor matching an unsupported join point kind on a class makes the first load of that class throw
`UnsupportedJoinpointException` naming the advisor and the kind; the class is then declared and linked, but not
woven. Narrow the pointcut to method execution, or run that application with the stream driver.

## Differences from the stream driver

- The woven class is the original class: no `FooOriginalTrait`, no proxy class, `Go\Aop\Proxy` is not implemented
  and `get_class()`/`instanceof` see no difference at all.
- Reflection of an advised method reports the donor file as the file of its body (`getFileName()`,
  `getStartLine()`), since that is where the dispatcher was compiled; the class itself keeps its original file.
- A private or final method whose body is a single literal may have been inlined by the opcache optimizer into its
  callers before the swap: those call sites keep the literal.
- `cache:warmup:aop` and `debug:weaving` work on the stream driver's cache and refuse a kernel configured for the
  engine driver. Warm an application running the engine driver by loading its classes (a smoke request), the donor
  index then serves every later request.

## Boot report

`Go\Instrument\ZEngine\ZEngineDriver::getBootReport($container)` returns what the boot did: PHP version, whether
opcache is active, and the JIT guard report (`jitWasActive`, `previousMode`, `disabledAtRuntime`).

## Development

`composer test:zengine` runs the `zengine` PHPUnit group, which is excluded from the default run and self-skips
where the engine cannot boot:

- `tests/Functional/ZEngine/` boots the engine in PHP subprocesses (both opcache legs) and compares the driver with
  the stream driver on the same fixtures;
- `tests/Instrument/ZEngine/InProcess/` boots z-engine inside the test runner and weaves the stubs of
  `tests/Stubs/ZEngine` for real, which is what gives the driver code coverage (the coverage job of
  `.github/workflows/phpunit.yml` installs z-engine and includes the group).

`.github/workflows/zengine.yml` runs the group on PHP 8.4 and 8.5 with the z-engine branch of each minor installed;
the other workflows do not install z-engine (`phpstan/zengine-stubs.php` declares the API the driver uses, for the
analysis without the package).
