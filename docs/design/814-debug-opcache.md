# Design: serving fresh woven classes from OPcache in debug mode (#814)

Status: proposal, no code. The issue asks for an approved design before anything is written, because the stream
filter exists for XDebug breakpoints and magic constants, which this touches.

## The problem

In debug mode every woven class goes through `php://filter/read=go.source.transforming.loader/resource=<original>`.
OPcache caches a script only when it comes from a plain `file://` or `phar://` path (`is_cacheable_stream_path()` in
`ZendAccelerator.c`), so a filtered include is compiled on every request. In the shop demo (94 woven classes) a hot
debug request takes 13.0 ms against 5.5 ms in production (`docs/performance.md`); per request the filter does, for
every woven class, a freshness check, a `file_get_contents()` of the cached copy and a full compile of it. The
untransformed files were already moved out of the filter (#745): a fresh record without `cacheUri` is included by its
original path and OPcache keeps it.

## Current flow (debug mode)

```
Composer autoload (AopComposerLoader::findFile)
  original->findFile(class)                      → /app/src/Service/Order.php
  isAllowedFilter(path)                          → below includePaths, not excluded
  class in classMap (woven)?  → yes: php://filter/.../resource=/app/src/Service/Order.php   (no own freshness check)
                              → no:  queryFreshCacheState(path): fresh + no cacheUri → the original path (opcache'd)
                                                                  stale or unknown   → php://filter
include(php://filter/...)
  SourceTransformingLoader::filter()
    resolveOriginalUri() → realpath of the resource
    getCachePathForResource()                    → loads _transformation.cache on first call (full metadata)
    findCachedContent(): queryFreshCacheState()  → filemtime + filesize of the source, container->isFreshSince()
      fresh, cacheUri   → file_get_contents(cacheUri), emitted as the stream content  ← compiled by PHP, never cached
      fresh, untransformed → the buffered original source
      stale/miss        → transformer chain, CacheFileWriter (atomic, opcache_invalidate), new record
    relocateProxyIncludes() (only while another cache directory is being built)
  the woven source ends with `include_once AOP_CACHE_DIR . '/src/Service/Order.php';`  ← the proxy, a plain path
```

So today a woven class costs **one filtered include (never cached) plus one plain include of the proxy (cached)**.
The proxy is already served from OPcache in debug mode; only the woven body (the `...OriginalTrait` file) is not.

`FilterInjectorTransformer::rewrite()` (with `Features::INTERCEPT_INCLUDES`) follows the same rule: production serves a
known file natively from the include map, debug mode always returns the filter URI.

## What differs between the two include styles

Measured on the fixture project (PHP 8.5.12, cache warmed up in debug mode, then one process with the `default`
(debug, filter) and one with the `production` (cache path) configuration on the same cache; `<p>` is the project
directory, `<c>` = `<p>/var/cache/aspect`):

| Observation                                                  | php://filter (debug today)       | cache path (production)              |
|--------------------------------------------------------------|----------------------------------|--------------------------------------|
| `ReflectionClass(Main)->getFileName()` (the proxy class)     | `<c>/src/Application/Main.php`   | `<c>/src/Application/Main.php`       |
| `ReflectionClass(MainOriginalTrait)->getFileName()`          | `<p>/src/Application/Main.php`   | `<c>/src/Application/MainOriginalTrait.php` |
| `ReflectionMethod(MainOriginalTrait::doSomething)` file:line | `<p>/src/Application/Main.php:17`| `<c>/src/Application/MainOriginalTrait.php:17` |
| `ReflectionMethod(Main::doSomething)` (proxy method)         | `<c>/src/Application/Main.php:97`| `<c>/src/Application/Main.php:97`    |
| `getFileName()` called inside the woven body (wrapped by MagicConstantTransformer::resolveFileName) | `<p>/src/Application/Main.php` | `<p>/src/Application/Main.php` |
| `__FILE__`/`__DIR__` inside the woven body                   | original path (rewritten to a literal at weave time) | identical: the literal is in the cached file |
| TypeError thrown by an intercepted call (type check in the proxy signature) | `<c>/src/Application/EdgeCaseDemo.php:52` | identical |
| Exception thrown inside an original method body              | original file, original line     | `<c>/.../XOriginalTrait.php`, same line |
| `get_included_files()`                                       | `<p>/src/Application/Main.php` + the proxy | `<c>/.../MainOriginalTrait.php` + the proxy |
| OPcache script list after the request                        | proxies only                     | proxies and woven bodies             |
| `_transformation.cache` loaded                               | yes (freshness checks)           | no (prod), yes with debug freshness checks |

Consequences for the user:

- **Stack traces, exception file and line, warnings from method bodies.** Today the original file and line; with the
  cache path the `...OriginalTrait.php` copy below the cache directory, same line number (the woven body preserves
  every line, see `src/Instrument/Transformer/Weaving/AGENTS.md`). Frames inside the proxy and the advice chain already
  show cache paths in both modes. Readability of a log line suffers a little; the line number stays usable.
- **XDebug breakpoints and step-through.** XDebug resolves a breakpoint by the file name PHP reports for the op_array.
  Through the filter that is the original path, so a breakpoint set in the IDE on `src/Service/Order.php:40` hits.
  With the cache path the engine reports `var/cache/aop/src/Service/OrderOriginalTrait.php:40`: the breakpoint never
  hits, and stepping into an intercepted method opens the cached copy in the IDE. An IDE path mapping can not repair
  it, because the file *name* differs (`OriginalTrait` suffix), not only the directory. The proxy frames open the
  cached proxy file in both modes already.
- **`__FILE__`/`__DIR__` and `ReflectionClass::getFileName()` inside woven code.** Identical: MagicConstantTransformer
  rewrites the constants to literals of the original path at weave time and wraps `getFileName()` calls (#679). The
  functional MagicConstantTest covers both modes; nothing changes here.
- **`ReflectionClass::getFileName()` from outside woven code** (DI containers tracking resources, Doctrine, PHPUnit).
  For the class itself (the proxy) it already returns the cache path in both modes; for the trait it would change
  from the original path to the cached copy. Symfony's container resource tracking reflects the class, not the
  trait, so it is unaffected; tooling that resolves the trait's file would see the cache directory.
- **Code coverage (pcov, XDebug coverage).** Coverage is keyed by the op_array file name. Through the filter the
  lines of the original file are reported covered (line-preserving, so correct). With the cache path the coverage
  lands on `var/cache/aop/.../XOriginalTrait.php`: the original file shows as uncovered, PHPUnit's `<source>` filter
  drops the cache files, and a report of a woven project loses every intercepted class. This is the strongest reason
  not to switch unconditionally: test suites run in debug mode.
- **Error messages of PHP itself** (deprecations, warnings in method bodies): file name changes as for exceptions.
- **Cross-platform paths.** The cache path is what the weaver wrote (`PathResolver::realpath()`-normalized, native
  separators, `\` → `/` only in the proxy include below the cache dir); on Windows the trait path has backslashes like
  every other include. OPcache keys by the resolved path, so case-insensitive file systems and symlinked cache
  directories behave as for any plain include. The filter has no such questions because it never caches.

## Freshness checks that remain

Whichever way the file is included, debug mode must decide per class whether the record is fresh:
`CachePathManager::queryFreshCacheState()` compares `filemtime` and `filesize` of the source with the record and asks
the container `isFreshSince(cachedAt)` (stat of the tracked kernel and aspect files; the redundant re-stats are #815).
The full `_transformation.cache` is loaded once per request for it (the include map carries no mtimes). None of that
goes away; what goes away per woven class is `file_get_contents()` of the cached copy plus its compile, about the same
~30 µs → ~10 µs step the untransformed files took in #745, times the compile of a class body, which dominates.
A stale record or a miss still goes through the filter and is woven again, so the write path is untouched.

OPcache interplay with a path include of a re-woven file: `CacheFileWriter` calls `opcache_invalidate($file, true)`
after every atomic rename, which drops the stale entry from the shared memory of the SAPI that wove it (an FPM worker
invalidates for all workers; a CLI process invalidates only its own SHM). With `opcache.validate_timestamps=1` (the
default, and what a development setup should keep) OPcache re-stats the file after `revalidate_freq` seconds anyway.
`opcache.file_update_protection=2` keeps a file written in the last two seconds out of the cache: the request that
re-weaves compiles it once more, the next one caches it. A developer running `validate_timestamps=0` with a cache
that is rebuilt in another process (CLI warm-up, FPM serving) would serve stale woven bodies until a reload; that is
already true for the proxies today and belongs in the documentation, not in code.

## Options

### 1. Opt-in flag: include fresh woven files by cache path

A `Features` bit (debug mode only), e.g. `Features::DEBUG_CACHE_INCLUDES`. With it, `AopComposerLoader::findFile()`
treats a fresh woven record like a fresh untransformed one and returns `cacheUri`; `FilterInjectorTransformer::rewrite()`
does the same for intercepted includes. Stale records and misses keep the filter.

- Pros: explicit, nothing changes for existing setups; the user who turns it on knows breakpoints and coverage move to
  the cache directory; trivial code (two branches already exist for untransformed files); easy to document next to
  `PREBUILT_CACHE`.
- Cons: the default stays slow; the flag is one more thing to know; set in the kernel options it is static, while
  the need (debugger attached or not) changes per run.
- Risks: low. A test suite with the flag on loses coverage of woven classes: must be documented.

### 2. Automatic: cache path unless a step debugger or a coverage driver is active

Detection: `extension_loaded('xdebug') && array_intersect(['debug', 'coverage'], xdebug_info('mode'))` or
`extension_loaded('pcov') && ini_get('pcov.enabled')` → keep the filter; otherwise include by path.

- Pros: fast by default for everybody who develops without XDebug (the common case with PHP 8 and Docker); nothing to
  configure; coverage runs keep their original paths.
- Cons: behaviour depends on the environment: the same code logs `var/cache/aop/.../XOriginalTrait.php` in one shell
  and `src/X.php` in another (`XDEBUG_MODE=debug` set or not). `xdebug_info('mode')` reports the configured mode, not
  whether a session is connected: with `xdebug.start_with_request=trigger` every request stays on the slow path even
  when nobody debugs. Other debuggers (phpdbg) are not detected. Harder to explain in a bug report.
- Risks: medium. Surprising paths in logs and in tools that reflect traits; a future XDebug mode name breaks the
  detection silently (falls back to the fast path, i.e. breakpoints stop hitting).

### 3. Keep the filter, make it cheaper

Drop the `_transformation.cache` load (carry mtime/size in `_include.cache`), cache the `isFreshSince()` answer per
request (#815), skip `realpath()` of the resource. The compile remains: OPcache will never cache a stream, and the
compile is the bulk of the 7.5 ms gap (94 bodies). Worth doing anyway as part of #815, but it does not solve #814.

### Not an option now: a layout that lets the IDE map paths

If the woven body were cached under the *same* file name as the original (`<cache>/src/Service/Order.php`) and the
proxy elsewhere, an IDE path mapping `<cache>/src` → `<app>/src` would make breakpoints hit on path includes, and
coverage could be remapped by tooling. The proxy currently owns that path (and the holder layout of #760 builds on
it), so this is a cache format change with its own issue; noted so the recommendation below is not mistaken for the
end of the road.

## Recommendation

**Option 1, with the detection of option 2 as a guard inside the flag.** The feature is opt-in, so no existing setup
changes, and a user who opts in is protected from the two cases that are known to break: while XDebug runs in
`debug` or `coverage` mode, or pcov is enabled, the flag is ignored and the filter serves the woven bodies with their
original paths. The guard is evaluated once per process. The documentation recommends the flag for everyday
development (`'features' => Features::DEBUG_CACHE_INCLUDES` in debug mode) and explains what moves to the cache
directory (exception file names of method bodies, the trait's reflection file). Promoting the behaviour to the default
can be decided later with real feedback, as a one-line change of the default.

### Implementation sketch

- `src/Aop/Features.php`: `DEBUG_CACHE_INCLUDES = 512` (name to be settled), added to `ALL`; doc block: "Debug mode:
  include fresh woven files by their cache path so that OPcache caches them; ignored while XDebug debugs or collects
  coverage, or pcov is enabled. Exceptions from method bodies and the trait's reflection then name the cached copy."
- `src/Instrument/ClassLoading/DebugIncludeMode.php` (new, `final`, `@internal`): `public static function
  includesWovenFilesByPath(array $options): bool` — flag set and no debugger/coverage driver active; result memoized in
  a static, `reset()` for tests. Detection as in option 2; `function_exists('xdebug_info')` guarded.
- `src/Instrument/ClassLoading/AopComposerLoader.php`, `findFile()` debug branch: today a woven class skips the own
  freshness check; with the mode on, call `queryFreshCacheState()` for woven classes too and return the record's
  `cacheUri` when fresh (`isFreshUntransformedFile()` becomes `freshIncludeFile(): ?string` returning the original
  path for an untransformed record, the `cacheUri` for a woven one when the mode is on, null otherwise → filter).
- `src/Instrument/Transformer/FilterInjectorTransformer.php`, `rewrite()`: in debug mode with the mode on, a fresh
  known record is served natively like in production (`findIncludeFile()` can not be used, it skips the freshness
  check; use `queryFreshCacheState()`).
- `SourceTransformingLoader`, `CachePathManager`, the weaver: untouched. `CacheFileWriter` already invalidates.
- Docs: `docs/configuration.md` features table, `docs/performance.md` ("What a request does in each mode" debug
  column, the debug paragraph under "Tuning OPcache"), `src/Instrument/AGENTS.md` init flow item 3.
- Tests:
  - `tests/Instrument/ClassLoading/AopComposerLoaderTest.php`: debug + flag: fresh woven record → `cacheUri`; stale →
    filter URI; flag without debug: production behaviour unchanged; flag with a forced "debugger active" answer →
    filter URI.
  - `tests/Instrument/ClassLoading/DebugIncludeModeTest.php`: detection matrix with the memoized state reset; the
    XDebug/pcov branches can only be exercised where the extension exists (`#[RequiresPhpExtension]`), the "no
    extension" branch everywhere.
  - `tests/Functional/DebugModeLoadingTest.php`: a `debug_cached` configuration in
    `tests/Fixtures/project/web/configuration.php` (debug + flag): `findFile(Main::class)` is the cache path of the
    woven body, the class loads and its advices run (same results as `default`), `opcache_is_script_cached()` of
    that path is true after the include in a process with `opcache.enable_cli=1 -d opcache.file_update_protection=0`,
    a touched source goes back through the filter and the next request through the path again; magic constants
    (`bin/magic-constants.php`) unchanged under the new configuration.
  - `tests/Functional/IncludeInterceptionTest.php`: intercepted include of a fresh woven file resolves to the cache
    path with the flag.

### Acceptance criteria

1. Hot debug request of the shop demo with the flag within ~1.5 ms of the production request (today 13.0 vs 5.5 ms);
   `file_get_contents` count of a hot request 0 (today 94), OPcache script list contains every woven body.
2. Without the flag: byte-identical behaviour to today (existing functional tests pass unchanged).
3. With the flag and `XDEBUG_MODE=debug` or `=coverage`, or pcov enabled: woven bodies are still served through the
   filter (the DebugModeLoadingTest assertion on the filter URI holds under that environment).
4. A changed source, aspect or kernel file is woven again on the next request with the flag on, and the request after
   that includes the new cached file (no stale OPcache entry, verified with `validate_timestamps=1` and `=0` in the
   same process).
5. `__FILE__`, `__DIR__` and `getFileName()` inside woven code resolve to the original paths with the flag on
   (MagicConstantTest under the new configuration).
6. Documentation states what moves to the cache directory with the flag (exception file names of method bodies, the
   trait's reflection file) and that coverage runs must not enable it unless pcov/XDebug coverage is active (then the
   guard applies).

## Open points

- Name of the flag (`DEBUG_CACHE_INCLUDES`, `OPCACHE_DEBUG`, `CACHED_DEBUG_INCLUDES`?).
- Whether `xdebug.mode=develop` alone (no debugger, no coverage) should also keep the filter: it only changes error
  display, so the fast path seems right, but the stack traces it beautifies would then show cache paths.
- Whether a later layout change (woven body under the original file name, see above) is wanted; it would make the
  IDE-mapping story work and could eventually retire the filter for fresh files altogether.
