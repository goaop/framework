# src/Instrument — AOP interception pipeline

## Init flow
1. AspectKernel::init() — singleton, registers deferred transformer services, calls configureAop()
2. SourceTransformingLoader — PHP stream filter (php://filter/read=go.source.transforming.loader/resource=... protocol); registered lazily on the first cache miss via ensureRegistered() (autoloader miss, include of a file unknown to the include map or any include in debug mode; a warm request never registers it)
3. AopComposerLoader::init() — hooks Composer autoloader → redirects through stream filter (production: woven classes via composer's class map; debug: a fresh untransformed record → original path, so opcache caches it; woven/stale/miss → php://filter)
4. Caching lives in SourceTransformingLoader::filter() — cache hit → cached content emitted as-is (no parsing, no transformers); miss → StreamMetaData + transformer chain → write cache
5. Freshness rule lives in one place: CachePathManager::queryFreshCacheState() (record filemtime/filesize, container isFreshSince(cachedAt); PREBUILT_CACHE trusts records), used by the filter and the debug autoloader
6. Building another cache directory: CacheWarmer(cacheDir: $dir) / cache:warmup:aop --cache-dir (workers get it too, WarmupWorker::main()) → CachePathManager::withCacheDir() flushes and parks the state of the kernel's directory, works on the other one from scratch, restores afterwards; the kernel keeps serving the process from its own directory (prod class map snapshot in AopComposerLoader). Woven sources served meanwhile get their `include_once AOP_CACHE_DIR . '...'` proxy includes (ProxyCache::CACHE_DIR_INCLUDE) pointed at the directory being built (SourceTransformingLoader::relocateProxyIncludes()); the cached files keep the constant. CachedAspectLoader reads the current directory from the manager, so advisor caches follow. Never re-init the kernel for another directory. CacheWarmer always goes through withCacheDir(), also in place: the directory is created when missing (a PREBUILT_CACHE kernel never creates it). flushCacheState() stamps _include.cache (inode/mtime/size, every write is an atomic rename): rewritten by another writer since loaded (warm-up into the directory, concurrent request, Symfony cache:clear swapping the warmed directory in) → the records on disk win, only records of sources unknown there are added

## Cache warmup (cache:warmup:aop)
- CacheWarmer (orchestrator): enumerates the files, weaves them in-process (Warmup\FileWarmer: php://filter read +
  ErrorException handler) or with Warmup\WorkerPool when `--workers` > 1 (default 1; no worker command line →
  in-process).
- Memory: the engine keeps every parsed syntax tree (~0.2 MB per file) unless capped: AspectKernel::configureReflection()
  (init) applies the `parsedFilesLimit` kernel option (positive int, default AspectKernel::DEFAULT_PARSED_FILES_LIMIT = 100,
  the one source of truth), FileWarmer sets `--cached-files`, which defaults to that option.
- Workers: `php -r` WarmupWorker::main() (autoloader + loader file + cached files as arguments, Console\KernelLoader boots like the
  command), SIGINT ignored, stray output swallowed by ob_start. Protocol (Warmup\WorkerProtocol): one line per message,
  marker + base64(serialize()); coordinator → `['file']`, closing stdin stops the worker; worker → ready/failed/result.
- Coordinator owns the queue (largest files first, FILES_IN_FLIGHT = 2 per worker) and the metadata: each result carries
  CachePathManager::takeNewCacheState() of the worker, merged with mergeCacheState() and flushed once at the end (also
  on interrupt). Workers never flush (several writers of _transformation.cache would drop each other's records).
- Worker death: first pending file fails with the stderr tail, the rest is requeued, a new worker starts; death before
  `ready` aborts (WeavingException::warmupWorkerFailedToStart). Worker stdout/stderr are `['socket']` descriptors:
  stream_select() can wait on sockets on every system, on pipes not on Windows.
- UI (Console\Warmup): FileListReporter with -v, else ProgressBarReporter (Symfony ProgressBar: redrawn in place on a
  decorated output, a line per tenth of the files elsewhere). Progress + errors → stderr, header + summary → stdout.

## Transformer chain (order matters)
Applied per loaded file. Each returns TransformerResult: Transformed|Abstain|Aborted (skips the rest of the chain, reverts to the original source, recorded as untransformed).

1. SyntaxTreeRewriter — framework service (FrameworkServices, so it survives an overridden registerTransformerServices()); walks the syntax tree once and dispatches every node to the NodeRewriter rules (container tag) declaring its type (`$nodeTypes`), in registration order. Rules are stateless per node (weaving re-enters the loader for other files mid-chain); ancestors are passed to the rules from the dispatcher's stack (no parent attributes on the shared tree, they cost ~3x a walk). PrefilteredNodeRewriter rules declare `$sourceMarkers`: looked up case-insensitively in StreamMetaData::$originalSource before the walk, a rule without a marker in the file is skipped, no rule left → no walk (rules-per-node-class cache keyed by the set of applicable rules). Built-in rules:
   - ConstructorExecutionTransformer — new expressions outside constant-expression contexts; always registered, `$sourceMarkers` is [] (rule off) while AdvisorIndex reports no KIND_INIT advisor; at runtime construct() decides once per class (strategy cache): InitializationAware → its `__initialization()`, instantiable (public/no constructor) → native `new $class(...$args)`, else (private/protected constructor, abstract, enum) a shared advice-less ReflectionConstructorInvocation, which ignores visibility like `new self()` in a factory (#818)
   - FilterInjectorTransformer — include/require (only if INTERCEPT_INCLUDES enabled); at runtime rewrite() serves, in production, a file known to the include map natively (original file if untransformed, recorded cacheUri if woven, no file_exists/freshness check, no _transformation.cache); unknown files and debug mode → php://filter
   - MagicConstantTransformer — `__FILE__`/`__DIR__` → original paths, wraps `getFileName()`; never counts as a transformation (only cache-dir files need it; PHP resolves magic constants of a `php://filter/.../resource=<path>` include to <path>)
2. WeavingTransformer — main; AdviceMatcher + CachedAspectLoader → proxy generators. Sees the token edits of the rules.

### Performance gate
Touching a SourceTransformer/NodeRewriter or the transformer wiring → run `composer test:performance:transformation` before and after; the numbers must not get worse.
- tests/Performance/TransformationPerformanceTest.php (`#[Group('transformation-performance')]`, excluded from the default run): times `transform()` of SyntaxTreeRewriter (default rules, all rules) over the nikic/php-parser sources and of WeavingTransformer over the weaving fixtures; ratio to a plain no-op NodeTraverser walk of the php-parser trees vs a budget constant.
- CI (.github/workflows/transformation-performance.yml) runs only for PRs touching src/Instrument/Transformer/**, SourceTransformingLoader, AspectKernel or FrameworkServices: alternates base/head `src/` 3× on one runner, fails on a head budget failure or a head ratio >10% above base (tests/Performance/compare-transformation-performance.php).

## Weaving internals
WeavingTransformer only orchestrates: load aspects → match advisors → TraitConverter → proxy generator → ProxyCache.
TokenStreamEditor, TraitConverter and ProxyCache (src/Instrument/Transformer/Weaving/) and how every step works:
src/Instrument/Transformer/Weaving/AGENTS.md.

## Trait-based proxy engine (4.0)
WeavingTransformer converts original class to trait + proxy class. Two generated files for class Ns\Foo:

### Woven file (replaces original in php stream filtering)
```php
trait FooOriginalTrait { /* original methods verbatim */ }
include_once AOP_CACHE_DIR . '/Foo.php';
```

Proxy path = source path below the cache dir (`/Foo.php`). A file with several woven class-likes gets one proxy per class
below its name, like a file inside a phar: `/Holder.php/Ns/First.php` (#760). Proxies are saved once the whole file is
woven (the layout depends on the woven class count); switching layouts removes the proxies of the other one.

### Proxy file (loaded by include_once)
```php
class Foo extends OriginalParent implements OriginalInterfaces, \Go\Aop\Proxy
{
    use \Ns\FooOriginalTrait {
        \Ns\FooOriginalTrait::interceptedMethod as private interceptedMethodOriginalAlias;
    }
    public function interceptedMethod(ArgType $arg): ReturnType {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, ReturnType> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'interceptedMethod',
            [
                Interceptor::before(The::aspect(SomeAspect::class)->adviceMethod(...)),
            ],
            $this->interceptedMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
}
```
Interceptor list entries are first-class advice callables on the aspect instance
(`The::aspect(X::class)->m(...)`); container-backed closure advices use
`The::advice('advisorId')` instead. Emitted by InterceptorListGenerator from
GeneratedInterceptor descriptors (string advisor ids are rejected).
Short names above are `use` aliases managed by ProxyImports: a name colliding with the original
file's imports or body gets an adjusted alias (e.g. `use Go\Aop\Framework\Interceptor as AopInterceptor;`),
see src/Proxy/AGENTS.md. WeavingTransformer passes the original imports to the proxy generator constructors.

### Key invariants
- Proxy re-inherits parent+interfaces via reflection (not from woven source)
- self:: in trait body → proxy class (no rewrite needed)
- Private methods interceptable (impossible with old extend-based engine)
- Intercepted properties are REMOVED from the trait body per PropertyItem, lines kept, `/* Moved by weaving interceptor … */` marker, never `//` (#669); details in Weaving/AGENTS.md
- FCC 4th arg to InterceptorInjector:
  - `$this->mOriginalAlias(...)` — own dynamic methods
  - `self::mOriginalAlias(...)` — own static methods
  - `parent::m(...)` — inherited methods (no trait alias)
  - `\fn(...)` — function proxies

## Line preservation: Woven trait line numbers (XDebug)
Woven trait MUST preserve original source line numbers for XDebug breakpoints: every edit keeps the newlines of the
tokens it touches (class/enum header, enum cases, attributes, properties, promoted parameters), see
src/Instrument/Transformer/Weaving/AGENTS.md. Proxy file (ClassProxyGenerator/EnumProxyGenerator): thin dispatch
wrapper — line numbers don't matter.

## PHP compat: #[\Override] on intercepted methods (8.3+)
PHP copies #[\Override] to the trait alias of an intercepted method (fatal error): TraitConverter strips it from the
woven source, the proxy method keeps it. A trait-imported method with #[\Override] can not be intercepted (#761,
WeavingException). Details and workarounds: src/Instrument/Transformer/Weaving/AGENTS.md.

## Aspects themselves
Classes implementing \Go\Aop\Aspect: unconditionally skipped by WeavingTransformer. Aspects cannot weave themselves.
The check (`implementsInterface(Aspect::class)`) runs in WeavingTransformer::weaveClass() only AFTER advices were found: it reflects
(and parses) every ancestor, so unadvised classes must never pay for it (#748).

## Partial reflection: ancestors that can't be located (Features::PARTIAL_REFLECTION)
Opt-in. AspectKernel::init() → configureReflection() sets
`ReflectionEngine::setMissingAncestorPolicy(MissingAncestorPolicy::Partial)` (goaop/parser-reflection 4.2/5.2) when the
flag is on, and leaves the engine alone otherwise (strict: a missing ancestor fails the class, WeavingException hint
#782). With the flag, a parent class, interface or trait whose source the locator can't find (generated at runtime,
`class_alias()`, outside the Composer autoloader) is left out of the reflection instead of failing the class.
Consequences:
- Matching sees the class's own members and the ones of its available ancestors; members inherited from a missing ancestor
  are never matched, so never intercepted.
- The proxy must still declare the missing parent/interfaces. ClassProxyGenerator takes the parent from
  `getParentClassName()` of the parsed reflection (the name as written, located or not) and the interfaces from
  `getInterfaceNames()`, which names the missing ones of a partial reflection; EnumProxyGenerator needs nothing more.
  Never read the class node for this: names of ancestors are parser-reflection's job. Missing traits need nothing: the
  `use` stays in the trait body.
A matching failure on an aspect is swallowed (aspects used to be skipped before matching); on other classes it is rethrown.
