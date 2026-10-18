# packages/stream-wrapper-driver — stream wrapper weaving driver
goaop/stream-wrapper-driver | NS: Go\Driver\StreamWrapper | `'driver' => StreamWrapperDriver::class`

## What it is
A source-transforming driver (Go\Driver\SourceTransformingDriver) that replaces PHP's `file://` stream wrapper
instead of wrapping composer's loader: every file included from within the weaving scope (appDir, includePaths,
excludePaths) is transformed at load time, whoever includes it. Same transformer chain, cache and warmup
(`cache:warmup:aop`) as the stream filter driver, so it covers the same join points.

## Mechanism (src/)
- StreamWrapperDriver::boot(): refuses Features::INTERCEPT_INCLUDES (StreamWrapperConfigurationException, includes
  are intercepted by nature and the flag would rewrite them into php://filter URIs), then AopStreamWrapper::register().
- AopStreamWrapper (`@internal`): `stream_wrapper_unregister('file')` + `stream_wrapper_register('file', ...)`. Every
  protocol method delegates to the native wrapper through `native()`: `stream_wrapper_restore('file')`, the native
  call, re-register in `finally`; a depth counter keeps nested calls from restoring twice. Opened handles are native
  streams (or php://memory), so reads/writes never re-enter the wrapper.
- stream_open(): opens natively first (a missing file fails natively). `($options & 0x80)` is the include flag
  (`STREAM_OPEN_FOR_INCLUDE` of main/php_streams.h, no userland constant). An include within the scope
  (Enumerator path filter on the PathResolver::realpath()-resolved path) runs
  SourceTransformingLoader::ensureRegistered() + transformSource() WITH the wrapper registered (the chain autoloads
  parent classes meanwhile, they must be woven too); a result differing from the source is served from php://memory.
- stream_stat() of a memory-served include: native stat() of the original path with `size`/[7] replaced by the served
  length. Verified facts (PHP 8.4/8.5): opcache caches a file served through a user `file://` wrapper under its
  original path as long as stream_stat() reports an mtime (and opcache.file_update_protection does not block it); PHP
  reads exactly `st_size` bytes of an included stream, so the size MUST be the served length.
- Lost: AopComposerLoader::findOriginalFile() finds no wrapped composer loader, the kernel falls back to reflection
  when tracking aspect files (debug mode only).

## Tests (tests/)
- `vendor/bin/phpunit --testsuite stream-wrapper-driver` (part of the default `composer test`); root `composer analyze`
  and `composer cs` cover the package.
- AopStreamWrapperTest: real temporary directories (Go\PhpUnit\UsesTemporaryDirectory), kernel/container stubs, a
  marker-rewriting SourceTransformer; always unregisters the wrapper in tearDown().
- Functional/: subclasses of the core ClassWeavingTest (debug + production) and MagicConstantTest on the fixture
  configurations `stream_wrapper`, `stream_wrapper_production`; StreamWrapperWeavingTest runs the probe
  tests/Fixtures/project/bin/driver-weaving.php under `stream_wrapper_methods` vs `ffi_reference` (stream filter
  driver, same FfiAspectKernel fixtures) with opcache off and on, warm cache included, and checks opcache lists the
  woven files under their original paths.
