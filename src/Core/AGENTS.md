# src/Core — Container and aspect loading

## Container (Container.php)
- DI container: add(by class-string|key), getService(), addLazyService(Closure)
- Automatic tagging by interface

## Aspect loading
- AspectLoader — scans aspect classes for pointcut/advice attributes → Advisor[]; CachedAspectLoader decorates it (both implement AspectLoaderInterface)

### Compiled advisor cache (CachedAspectLoader + AdvisorCacheCompiler)
- Format: includable plain-PHP file returning `['version' => AdvisorCacheCompiler::VERSION, 'advisors' => [...]]`; the graph is nested static constructor expressions (CompilableToPhp, see src/Aop/AGENTS.md); byte-deterministic output (DebugWeavingCommand double-warmup must stay diff-free)
- File naming: aspect source path with appDir prefix replaced by cacheDir and `.php` → `.cache.php` ({appDir}/src/Aspect/X.php → {cacheDir}/src/Aspect/X.cache.php); aspect not under appDir or without a file → direct load, never cached
- Advisor keys emitted as `Fqcn::class . '->member'` concat — runtime ids byte-identical to the direct loader
- loadFromCache: guarded include; Throwable → E_USER_WARNING + empty result (fallback); clean version mismatch → SILENT rebuild (expected upgrade path)
- saveToCache: NotCompilableException → E_USER_WARNING + skip write entirely (never a half file); writes via Instrument\FileSystem\CacheFileWriter (atomic, no exec bits, opcache_invalidate)
- PREBUILT_CACHE: existing file trusted without freshness checks; unusable file → direct loader, NEVER writes (read-only FS safe)
- AdvisorCacheCompiler/AdvisorCachePrinter/NotCompilableException are @internal — free to break between releases
- AttributeAspectLoaderExtension — handles PHP 8 attribute-based aspect definitions; throws AspectException for non-public advice methods (first-class callable advices require public visibility; #[Pointcut]-only methods exempt)
- AdviceMatcher — given class reflector, returns applicable advisors keyed by join point
  - Scans IS_PUBLIC|IS_PROTECTED|IS_PRIVATE methods
  - Private methods from parent classes excluded

## Bridge
src/Bridge/Doctrine/MetadataLoadInterceptor.php — workaround for Doctrine ORM entity weaving (Doctrine loads metadata before kernel can intercept classes).
