# src/Core/Cache — compiled advisor cache (Go\Core\Cache, all @internal)

## Classes
- CachedAspectLoader — AspectLoaderInterface decorator over AspectLoader; reads/writes the compiled cache
- AdvisorCacheCompiler — renders loaded items into includable plain-PHP cache file content (VERSION const)
- AdvisorCachePrinter — pretty-printer (extends Proxy\Generator\GeneratedCodePrinter; multiline arrays/news)
- CacheFileWriter — mkdir-recursive, ATOMIC same-dir tmp+rename writes (one universal path, no LOCK_EX: the unique tmp name makes locking redundant, and the same code runs on stream wrapper paths - tests use goaop/virtual-file-system), strips exec bits, opcache_invalidate; also used by Instrument\ClassLoading\CachePathManager for woven classes
- NotCompilableException — see src/Aop/AGENTS.md (Compilable section)

## Format & naming
- File returns `['version' => AdvisorCacheCompiler::VERSION, 'advisors' => [...]]`; the graph is nested static constructor expressions (Compilable, see src/Aop/AGENTS.md); byte-deterministic output (DebugWeavingCommand double-warmup must stay diff-free)
- Naming: aspect source path with appDir prefix replaced by cacheDir and `.php` → `.cache.php` ({appDir}/src/Aspect/X.php → {cacheDir}/src/Aspect/X.cache.php); aspect not under appDir or without a file → direct load, never cached
- Advisor keys emitted as `Fqcn::class . '->member'` concat — runtime ids byte-identical to the direct loader
- compile() signature is variadic: `compile(string $aspectClassName, Pointcut|Advisor ...$items)` — string advisor ids survive argument unpacking as names; integer-like ids degrade to positional keys renumbered from zero
- `use` block: single unambiguous short names imported, collisions stay FQ inline, global (single-part) names never imported (namespace-less file)

## Error handling — throw, never warn (maintainer rule: no catch + trigger_error)
- loadFromCache: bare include (scope-isolated static closure); a corrupt/not-includable file THROWS (ParseError etc.) — writes are atomic, so corruption means external interference; clean version/shape mismatch → silent [] → rebuild (normal) / direct-loader fallback (prebuilt) — the expected upgrade path
- saveToCache: NotCompilableException PROPAGATES (no file written, never a half file) — such an aspect cannot run with the advisor cache enabled
- PREBUILT_CACHE: existing file trusted without freshness checks; wrong version/shape → direct loader, NEVER writes (read-only FS safe); a corrupt file still throws
