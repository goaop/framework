# src/Instrument — AOP interception pipeline

## Init flow
1. AspectKernel::init() — singleton, registers deferred transformer services, calls configureAop()
2. SourceTransformingLoader — PHP stream filter (php://filter/read=go.source.transforming.loader/resource=... protocol); registered lazily on the first cache miss via ensureRegistered()
3. AopComposerLoader::init() — hooks Composer autoloader → redirects through stream filter (production: woven classes via composer's class map; debug: a fresh untransformed record → original path, so opcache caches it; woven/stale/miss → php://filter)
4. Caching lives in SourceTransformingLoader::filter() — cache hit → cached content emitted as-is (no parsing, no transformers); miss → StreamMetaData + transformer chain → write cache
5. Freshness rule lives in one place: CachePathManager::queryFreshCacheState() (record filemtime/filesize, container isFreshSince(cachedAt); PREBUILT_CACHE trusts records), used by the filter and the debug autoloader

## zengine driver (src/Instrument/ZEngine/, `'driver' => 'zengine'`)
Runtime weaving through lisachenko/z-engine (FFI) in place of steps 2-4 above; AspectKernel::init() branches on the driver. ZEngineDriver::boot(): requires z-engine (class_exists Core), JitGuard::enforce() (JIT must be off; PHP 8.5: ini_set('opcache.jit','off') at runtime, reported), Core::init(), registers DonorCacheIndex / MethodTableRewriter / ZEngineClassWeaver as lazy services, ZEngineComposerLoader::init() (wraps composer's loader, unwrapping either driver's wrapper: ComposerLoaderDecorator).
- ZEngineComposerLoader::loadClass(): include the original file natively, then ZEngineClassWeaver::weaveLoadedClass() when the path filter allows (the subclass that triggered the autoload links against the already woven parent).
- ZEngineClassWeaver: once per class per request (failures remembered and rethrown); DonorCacheIndex::findFresh() → warm: include donor + rewire; miss: load aspects, AdviceMatcher on the parsed source (ReflectionFile, native fallback), refuse non-method kinds (UnsupportedJoinpointException::forClass), DonorClassGenerator → `{cacheDir}/_zengine/<path>/<Short>__AopDonor.php`, record. `weave($class)` is the public entry for classes declared before the kernel.
- MethodTableRewriter: own method → `$class->getMethod($m)->redefine($donorMethod, preserveAs: $m . 'OriginalAlias')`; inherited → `$class->addMethod($m, $donorMethod)`; EngineClass::fromClassTable() re-resolved per method (opcache copy-out repoints the bucket); z-engine refusals → UnsupportedJoinpointException::engineRefusal().
- DonorCacheIndex: `{cacheDir}/_zengine.cache` (version, classes: source/filemtime/filesize/cachedAt/donor/donorClass/methods), freshness = CachePathManager::isFreshRecord() (the one shared rule), written via CachePathManager::writeIndexFile(), flushed at destruct. Separate from _transformation.cache on purpose (a record without cacheUri means "serve natively" to the stream driver).
- Stream-only: Features::INTERCEPT_* and PREBUILT_CACHE (refused in normalizeOptions), CacheWarmer/debug:weaving (refused), property/introduction/init join points.

## Transformer chain (order matters)
Applied per loaded file. Each returns TransformerResult: Transformed|Abstain|Aborted (skips the rest of the chain, reverts to the original source, recorded as untransformed).

1. SyntaxTreeRewriter — framework service (FrameworkServices, so it survives an overridden registerTransformerServices()); walks the syntax tree once and dispatches every node to the NodeRewriter rules (container tag) declaring its type (`getNodeTypes()`), in registration order. Rules are stateless per node (weaving re-enters the loader for other files mid-chain); ancestors are passed to the rules from the dispatcher's stack (no parent attributes on the shared tree, they cost ~3x a walk). PrefilteredNodeRewriter rules declare getSourceMarkers(): looked up case-insensitively in StreamMetaData::$originalSource before the walk, a rule without a marker in the file is skipped, no rule left → no walk (rules-per-node-class cache keyed by the set of applicable rules). Built-in rules:
   - ConstructorExecutionTransformer — new expressions outside constant-expression contexts (only if INTERCEPT_INITIALIZATIONS enabled)
   - FilterInjectorTransformer — include/require (only if INTERCEPT_INCLUDES enabled)
   - MagicConstantTransformer — `__FILE__`/`__DIR__` → original paths, wraps `getFileName()`; never counts as a transformation (only cache-dir files need it; PHP resolves magic constants of a `php://filter/.../resource=<path>` include to <path>)
2. WeavingTransformer — main; AdviceMatcher + CachedAspectLoader → proxy generators. Sees the token edits of the rules.

### Performance gate
Touching a SourceTransformer/NodeRewriter or the transformer wiring → run `composer test:performance:transformation` before and after; the numbers must not get worse.
- tests/Performance/TransformationPerformanceTest.php (`#[Group('transformation-performance')]`, excluded from the default run): times `transform()` of SyntaxTreeRewriter (default rules, all rules) over the nikic/php-parser sources and of WeavingTransformer over the weaving fixtures; ratio to a plain no-op NodeTraverser walk of the php-parser trees vs a budget constant.
- CI (.github/workflows/transformation-performance.yml) runs only for PRs touching src/Instrument/Transformer/**, SourceTransformingLoader, AspectKernel or FrameworkServices: alternates base/head `src/` 3× on one runner, fails on a head budget failure or a head ratio >10% above base (tests/Performance/compare-transformation-performance.php).

## Trait-based proxy engine (4.0)
WeavingTransformer converts original class to trait + proxy class. Two generated files for class Ns\Foo:

### Woven file (replaces original in php stream filtering)
```php
trait FooOriginalTrait { /* original methods verbatim */ }
include_once AOP_CACHE_DIR . '/Foo.php';
```

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
- Intercepted properties are REMOVED from the trait body per PropertyItem (removeInterceptedPropertyItems): whole statement blanked when all items move, else only the item + its separating comma; blanked tokens keep their newlines (line numbers), a newline-free `/* Moved by weaving interceptor … */` marker is left; never use a `//` prefix (multi-line declarations would stay live, issue #669)
- FCC 4th arg to InterceptorInjector:
  - `$this->mOriginalAlias(...)` — own dynamic methods
  - `self::mOriginalAlias(...)` — own static methods
  - `parent::m(...)` — inherited methods (no trait alias)
  - `\fn(...)` — function proxies

## Line preservation: Woven trait line numbers (XDebug)
Woven trait MUST preserve original source line numbers for XDebug breakpoints.
- Class→trait: convertClassToTrait() replaces `class` keyword, strips modifiers/extends/implements. All other tokens (incl. blank lines) kept in place.
- Enum→trait: convertEnumToTrait() replaces removed tokens (cases, backed type, implements) with equal number of newlines to keep methods at original line positions.
- Proxy file (ClassProxyGenerator/EnumProxyGenerator): thin dispatch wrapper — line numbers don't matter.

## PHP compat: #[\Override] on intercepted methods (8.3+)
When intercepted method has #[\Override], PHP copies attribute to trait alias — fatal error (alias doesn't override anything).
WeavingTransformer::convertClassToTrait() strips #[\Override] from trait for every intercepted method. Attribute preserved on proxy's override method (proxy extends same parent).

## Aspects themselves
Classes implementing \Go\Aop\Aspect: unconditionally skipped by WeavingTransformer. Aspects cannot weave themselves.
The check (`implementsInterface(Aspect::class)`) runs in processSingleClass() only AFTER advices were found: it reflects
(and parses) every ancestor and throws when one can't be located, so unadvised classes must never pay for it (#748).
A matching failure on an aspect is swallowed (aspects used to be skipped before matching); on other classes it is rethrown.
