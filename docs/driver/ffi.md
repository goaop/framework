# The ffi weaving driver (experimental)

Package `goaop/ffi-driver`, class `Go\Driver\Ffi\FfiDriver`.

Runtime method-table weaving through [lisachenko/z-engine](https://github.com/lisachenko/z-engine) (PHP FFI): classes
load natively and, right after a class is loaded, the body of every advised method is swapped in place with a generated
dispatcher while the original body stays callable under a private alias. No source rewriting, no stream filter or
wrapper, no proxy class, no include interception.

Everything above the driver is shared with the [other drivers](README.md): aspects, pointcuts, the advisor cache, the
interceptor chain and the join-point API. An aspect written for one driver runs unchanged on the other, within the
limits listed below.

## Requirements

- `ext-ffi` with `ffi.enable=1` (or `preload` with a preload script booting z-engine).
- `lisachenko/z-engine`. Every release of it targets one PHP minor, and composer picks the release matching the PHP
  running the install:

  ```bash
  composer require goaop/ffi-driver
  composer require lisachenko/z-engine
  ```

  A project installed on several PHP minors constrains the engine accordingly, e.g. `"lisachenko/z-engine": "~8.4.0|~8.5.0"`.
- The opcache JIT **off** for the whole process: `opcache.jit=off` and `opcache.jit_buffer_size=0` in `php.ini`, the
  FPM pool or `php -d`. z-engine hooks the executor internals the JIT bypasses. A JIT found active is switched off
  at runtime as a safety net and reported in the [boot report](#boot-report); the process-level setting stays the
  requirement, since code compiled before the kernel ran keeps its JIT state.

A kernel configured for the driver without z-engine, without FFI or with a JIT that cannot be switched off throws
`Go\Driver\Ffi\Exception\EngineConfigurationException` (a `Go\Aop\Exception\InvalidConfigurationException`) from
`init()` with the missing requirement in the message.

## Configuration

```php
use Go\Driver\Ffi\FfiDriver;

ApplicationAspectKernel::getInstance()->init([
    'driver'       => FfiDriver::class,
    'debug'        => false,
    'appDir'       => __DIR__ . '/..',
    'cacheDir'     => __DIR__ . '/../var/cache/aop',
    'includePaths' => [__DIR__ . '/../src'],
]);
```

`includePaths`, `excludePaths`, `debug`, `cacheDir` and `cacheFileMode` mean the same as with the stream filter
driver; the installed z-engine package joins the excluded paths. The `features` option must not contain
`Features::INTERCEPT_INCLUDES`: includes are rewritten by a source transformer, and this driver never rewrites
sources (`EngineConfigurationException` from `init()`).

## How a class is woven

1. The driver wraps composer's class loader with `Go\Driver\Ffi\FfiComposerLoader`, which includes the original file
   exactly as composer would (opcache caches it, magic constants and line numbers are untouched).
2. Right after the include, `Go\Driver\Ffi\ClassWeaver` matches the advisors against the class (from the parsed
   source, so attributes, parameter defaults and the file's imports are seen as written; native reflection is the
   fallback for a file the parser cannot read) and generates the **donor trait** `Ns\FooOriginalTrait` of `Ns\Foo`
   (`Go\Driver\Ffi\DonorTraitGenerator`): a trait in the namespace of the woven class, with the imports of its file,
   declaring exactly the advised methods with the dispatcher bodies a proxy class would get
   (`static $__joinPoint = InterceptorInjector::forMethod(...)`). The name carries the suffix of the stream filter
   driver's trait codegen (`AspectContainer::ORIGINAL_TRAIT_SUFFIX`), but the trait is never used by a class: the
   engine adopts its method bodies. Compiled in a trait, `self::class` and `parent::` resolve at run time against the
   woven class, so only the `self` and `parent` keywords of parameter and return types are spelled out as the classes
   they name (the engine compares the donor signature with the swapped entry).
   The donor file is written to `{cacheDir}/_ffi/<path relative to appDir>/<Short>OriginalTrait.php` and recorded in
   the index `{cacheDir}/_ffi.cache` together with the advised method names.
3. `Go\Driver\Ffi\MethodTableRewriter` hands the class and its donor to z-engine:
   - a method the class declares itself keeps its body under the private alias `<method>OriginalAlias` and gets the
     dispatcher body swapped in place (`ReflectionMethod::redefine($donorMethod, preserveAs: ...)`); the dispatcher
     calls `$this-><method>OriginalAlias(...)` like a trait-based proxy;
   - a method the class only inherits gets an own override adopting the dispatcher body
     (`ReflectionClass::addMethod($method, $donorMethod)`), whose dispatcher reaches the original through
     `parent::<method>(...)`.
4. The rewired class is marked with the `Go\Aop\Proxy` interface (`ReflectionClass::addInterfaces()`), like a
   generated proxy class: `instanceof Proxy` tells a woven object apart under every driver.

A warm request finds a fresh record in the index (same freshness rule as the stream filter driver: source size and
mtime, tracked resources in debug mode), loads the donor file and rewires the class without loading the aspects or
running the matcher. A class no advisor matches is recorded as well, so a warm request never matches it again.

The index is separate from the stream filter driver's `_transformation.cache` on purpose: a record without a cache
URI means "serve natively, never weave" to that driver, so engine records would poison a cache directory later used
by it.

### Function interception

An `execution(Ns\function(*))` pointcut needs no flag: the driver serves it exactly the way the stream filter driver
does, and without touching the engine. An advised internal function called **unqualified** from a namespace is
shadowed by a namespaced proxy function of the same name (`Ns\array_sum()` dispatching to the advices and reaching
`\array_sum()`), which PHP resolves such a call to before the global function. The proxies of a namespace are
generated into `{cacheDir}/_ffi/_functions/{Ns/Path}.php`, recorded in the donor index with the function names, and
included right after the file declaring the first loaded class of that namespace, before any code of the namespace
runs. A warm request includes the recorded file without loading the aspects.

Two consequences of that mechanism: a fully-qualified call (`\array_sum()`) is never intercepted, like with the stream
filter driver; and a call site that already ran before the proxies were declared (a class loaded before the kernel)
keeps the global function it resolved, since PHP caches the resolution per call site. Functions called from the global
namespace cannot be intercepted by any driver: a proxy there would redeclare the internal function.

### Classes declared before the kernel

Classes declared before the kernel was initialized (required directly, never autoloaded) are not seen by the loader.
Weave them on demand:

```php
$weaver = $kernel->getContainer()->getService(Go\Driver\Ffi\ClassWeaver::class);
$result = $weaver->weave(App\Legacy::class);   // Go\Driver\Ffi\WeaveResult: outcome and rewired methods
```

Weave before instances exist and before subclasses link: the engine mutation reaches neither objects created earlier
(under opcache) nor, for a class published in opcache shared memory, subclasses that were linked against the shared
entry (z-engine refuses that mutation, the driver reports it as `UnsupportedJoinpointException`).

## Supported join points

The driver rewires **method bodies**: `execution(...)` pointcuts on instance and static methods, public, protected
and private, declared or inherited, in regular classes (final, abstract and readonly classes included), and serves
**function execution** pointcuts through namespaced proxies (see above).

Everything else only a source-transforming driver provides, since it needs rewritten sources:

- property access (`access(...)` pointcuts),
- introductions (interfaces and traits),
- `initialization(...)` and `staticinitialization(...)` pointcuts,
- include interception (`Features::INTERCEPT_INCLUDES`),
- traits, enums and interfaces as weaving targets (skipped, never woven).

An advisor matching an unsupported join point kind on a class makes the first load of that class throw
`Go\Driver\Ffi\Exception\UnsupportedJoinpointException` naming the advisor and the kind; the class is then declared
and linked, but not woven. Narrow the pointcut to method execution, or run that application with the stream filter
or the stream wrapper driver.

The exceptions of the package extend the framework's: `EngineConfigurationException` is an
`InvalidConfigurationException`, `FfiWeavingException` (donor cannot be generated, written or loaded) is a
`Go\Aop\Exception\WeavingException`, and `UnsupportedJoinpointException` is an `FfiWeavingException`.

## Differences from the source-transforming drivers

- The woven class is the original class: no proxy class, `get_class()` sees no difference. It does implement
  `Go\Aop\Proxy` (added at runtime), so `instanceof Proxy` works like with a generated proxy.
- Reflection of an advised method reports the donor file as the file of its body (`getFileName()`,
  `getStartLine()`), since that is where the dispatcher was compiled; the class itself keeps its original file.
- A private or final method whose body is a single literal may have been inlined by the opcache optimizer into its
  callers before the swap: those call sites keep the literal.
- A call site that ran before the function proxies of its namespace were declared keeps the global function.
- `cache:warmup:aop` and `debug:weaving` transform sources through the transformer chain and refuse a kernel
  configured for this driver (`InvalidConfigurationException`: the cache warmer requires a
  `Go\Driver\SourceTransformingDriver`). Warm an application running this driver by loading its classes (a smoke
  request), the donor index then serves every later request.

## Boot report

`Go\Driver\Ffi\FfiDriver::getBootReport($container)` (container key `kernel.ffi.boot`) returns what the boot did:
the driver class, PHP version, whether opcache is active, and the JIT guard report (`jitWasActive`, `previousMode`,
`disabledAtRuntime`).

## Development

The package lives in `packages/ffi-driver/` of the framework repository and is mapped by the root `autoload-dev`.
Its tests form the `ffi-driver` PHPUnit testsuite, excluded from `composer test`:

```bash
composer require --dev lisachenko/z-engine   # never part of the framework's own dependencies
composer test:ffi                            # ffi on, JIT off
composer analyze:ffi                         # phpstan with packages/ffi-driver/phpstan.neon
```

The tests self-skip where the engine cannot boot:

- `packages/ffi-driver/tests/Functional/` runs the probe `tests/Fixtures/project/bin/driver-weaving.php` in PHP
  subprocesses (both opcache legs) with the `ffi`, `ffi_production` and `ffi_unsupported` configurations of the
  fixture project and compares the reports with the stream filter driver on the same fixtures (`ffi_reference`);
- `packages/ffi-driver/tests/InProcess/` boots z-engine inside the test runner and weaves the stubs of
  `packages/ffi-driver/tests/Stubs` for real, which is what gives the driver code coverage.

`.github/workflows/ffi-driver.yml` is the only workflow installing z-engine (`composer require --dev
lisachenko/z-engine`, composer resolves the release of the runner's PHP minor) and runs the suite on PHP 8.4 and 8.5.
