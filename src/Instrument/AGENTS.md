# src/Instrument — AOP interception pipeline

## Init flow
1. AspectKernel::init() — singleton, registers deferred transformer services, calls configureAop()
2. SourceTransformingLoader — PHP stream filter (php://filter/read=go.source.transforming.loader/resource=... protocol); registered lazily on the first cache miss via ensureRegistered()
3. AopComposerLoader::init() — hooks Composer autoloader → redirects through stream filter (production: woven classes via composer's class map; debug: a fresh untransformed record → original path, so opcache caches it; woven/stale/miss → php://filter)
4. Caching lives in SourceTransformingLoader::filter() — cache hit → cached content emitted as-is (no parsing, no transformers); miss → StreamMetaData + transformer chain → write cache
5. Freshness rule lives in one place: CachePathManager::queryFreshCacheState() (record filemtime/filesize, container isFreshSince(cachedAt); PREBUILT_CACHE trusts records), used by the filter and the debug autoloader

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

### Several woven class-likes in one file (#760)
One proxy file per source file, never one per class: processSingleClass() only converts the class and returns a
WovenProxy; transform() collects them in a local list (transform() is re-entered mid-transform) and writeProxies()
writes them once (ProxyFileGenerator) and appends ONE include_once.
- One proxy: byte-identical to the single-class output (`<?php`, optional `declare(strict_types=1);`, `namespace X;` ...).
- Several: `declare(strict_types=1);` once, then one braced block per proxy (`namespace X { use ...; class ... }`,
  `namespace { ... }` for global classes), even for the same namespace (each keeps its own ProxyImports). Source
  order, a proxy extending another proxy of the file after it (ProxyIncludePlanner::sortForProxyFile()).
- Include point (ProxyIncludePlanner::findIncludePoint()): after the last woven class-like whose proxy depends on its
  position, else after the first one. Position-bound = its woven trait keeps trait uses (PHP declares such a trait at
  runtime) or it extends/implements an UNWOVEN class-like of the file. A trait without trait uses is declared at compile
  time, so proxies of classes declared after the include still find their trait.
- Gap → WeavingException with the file, the woven class-likes, the include point and each offending line: a class-like
  declared up to the include point that needs a woven class-like of the file (unwoven: extends/implements/uses; woven:
  its trait uses), or a top-level statement between the first woven class-like and the include point that loads an
  earlier woven class-like (`new X`, `X::m()`, `X::$p`, `X::C`, classes it declares; not `X::class`, `instanceof`,
  types, function/closure/arrow function bodies — LoadedClassNameCollector).
- The runtime class map maps every class of the file to its woven file (CachePathManager, unchanged).

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
