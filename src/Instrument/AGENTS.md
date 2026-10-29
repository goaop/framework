# src/Instrument — AOP interception pipeline

## Init flow
1. AspectKernel::init() — singleton, registers deferred transformer services, calls configureAop()
2. SourceTransformingLoader — PHP stream filter (php://filter/read=go.source.transforming.loader/resource=... protocol); registered lazily on the first cache miss via ensureRegistered()
3. AopComposerLoader::init() — hooks Composer autoloader → redirects through stream filter (production: woven classes via composer's class map; debug: a fresh untransformed record → original path, so opcache caches it; woven/stale/miss → php://filter)
4. Caching lives in SourceTransformingLoader::filter() — cache hit → cached content emitted as-is (no parsing, no transformers); miss → StreamMetaData + transformer chain → write cache
5. Freshness rule lives in one place: CachePathManager::queryFreshCacheState() (record filemtime/filesize, container isFreshSince(cachedAt); PREBUILT_CACHE trusts records), used by the filter and the debug autoloader

## Transformer chain (order matters)
Applied per loaded file. Each returns TransformerResult: Transformed|Abstain|Aborted (skips the rest of the chain, reverts to the original source, recorded as untransformed).

1. SyntaxTreeRewriter — framework service (FrameworkServices, so it survives an overridden registerTransformerServices()); walks the syntax tree once and dispatches every node to the NodeRewriter rules (container tag) declaring its type (`$nodeTypes`), in registration order. Rules are stateless per node (weaving re-enters the loader for other files mid-chain); ancestors are passed to the rules from the dispatcher's stack (no parent attributes on the shared tree, they cost ~3x a walk). PrefilteredNodeRewriter rules declare `$sourceMarkers`: looked up case-insensitively in StreamMetaData::$originalSource before the walk, a rule without a marker in the file is skipped, no rule left → no walk (rules-per-node-class cache keyed by the set of applicable rules). Built-in rules:
   - ConstructorExecutionTransformer — new expressions outside constant-expression contexts; always registered, `$sourceMarkers` is [] (rule off) while AdvisorIndex reports no KIND_INIT advisor
   - FilterInjectorTransformer — include/require (only if INTERCEPT_INCLUDES enabled); at runtime rewrite() serves, in production, a file known to the include map natively (original file if untransformed, recorded cacheUri if woven, no file_exists/freshness check, no _transformation.cache); unknown files and debug mode → php://filter
   - MagicConstantTransformer — `__FILE__`/`__DIR__` → original paths, wraps `getFileName()`; never counts as a transformation (only cache-dir files need it; PHP resolves magic constants of a `php://filter/.../resource=<path>` include to <path>)
2. WeavingTransformer — main; AdviceMatcher + CachedAspectLoader → proxy generators. Sees the token edits of the rules.

## Weaving internals (src/Instrument/Transformer/Weaving/, all `@internal`)
WeavingTransformer only orchestrates: load aspects → match advisors per class-like/namespace → TraitConverter → proxy generator → ProxyCache.
- TokenStreamEditor — line-preserving token surgery on one StreamMetaData (blank keeps newlines, remove, removeWithInlineWhitespace, removeAttributes, skipAttributeGroup, find, append/prepend); static rangeOf(Node) / declarationRange(ClassLike) (skips class attribute groups, #598; throws without token positions). One instance per transform(); unit-tested directly (TokenStreamEditorTest).
- TraitConverter — class/enum/trait → woven trait: header rewrite (modifiers, keyword → `trait`, rename, extends/implements/backed type blanked), enum cases removed, trait-incompatible class attributes (#615). Delegates to OverrideAttributeStripper and InterceptedPropertyRemover (→ PromotedPropertyDemoter). Edit order per kind is fixed (blanked `#[` tokens would break later bracket scans).
- InterceptedMembers — intercepted method/property names from the sorted advices; the converters never read the advice array layout.
- AttributeName — resolved attribute class name, isOverride().
- ProxyCache — writes class proxies (one-class/multi-class layouts, #760) and namespace function proxies (`/_functions/`, freshness via container), returns the include statements.
- WovenClass — class + proxy code + last token, buffered until the file is woven.

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
- Intercepted properties are REMOVED from the trait body per PropertyItem (InterceptedPropertyRemover): whole statement blanked when all items move, else only the item + its separating comma; blanked tokens keep their newlines (line numbers), a newline-free `/* Moved by weaving interceptor … */` marker is left; never use a `//` prefix (multi-line declarations would stay live, issue #669)
- FCC 4th arg to InterceptorInjector:
  - `$this->mOriginalAlias(...)` — own dynamic methods
  - `self::mOriginalAlias(...)` — own static methods
  - `parent::m(...)` — inherited methods (no trait alias)
  - `\fn(...)` — function proxies

## Line preservation: Woven trait line numbers (XDebug)
Woven trait MUST preserve original source line numbers for XDebug breakpoints.
- Class→trait: TraitConverter replaces `class` keyword, strips modifiers/extends/implements. All other tokens (incl. blank lines) kept in place.
- Enum→trait: TraitConverter blanks the backed type and implements (newlines kept) and removes the case tokens, keeping the whitespace after each case, so methods stay at their original line positions.
- Proxy file (ClassProxyGenerator/EnumProxyGenerator): thin dispatch wrapper — line numbers don't matter.

## PHP compat: #[\Override] on intercepted methods (8.3+)
When intercepted method has #[\Override], PHP copies attribute to trait alias — fatal error (alias doesn't override anything).
OverrideAttributeStripper::strip() strips #[\Override] from the woven source for every intercepted own method: classes, enums and woven traits (renamed original trait, its methods aliased by TraitProxyGenerator). Attribute preserved on the proxy method (MethodGenerator copies it from the AST), so PHP still checks the override.
- Limitation (#761): a method a class/enum/trait imports from ANOTHER trait with #[\Override] can not be intercepted — the attribute lives in the shared trait file, can't be stripped per class. OverrideAttributeStripper::assertStrippable() (first step of TraitConverter::convert(), before any edit and the generators; native reflection hides the trait) throws WeavingException naming class, method, trait. Covers aliases, static methods, enums, woven traits using a trait.
- Workaround: `&& !matchInherited()` (excludes trait-imported methods too), narrower `&& !execution(public Foo->hello(*))`, drop #[\Override] from the trait method, or advise the trait itself (`execution(public HelloTrait->hello(*))`, intercepts it in every class using the trait).
- Trait methods inherited via a parent class are fine: proxy calls `parent::m(...)`, no alias.

## Aspects themselves
Classes implementing \Go\Aop\Aspect: unconditionally skipped by WeavingTransformer. Aspects cannot weave themselves.
The check (`implementsInterface(Aspect::class)`) runs in WeavingTransformer::weaveClass() only AFTER advices were found: it reflects
(and parses) every ancestor and throws when one can't be located, so unadvised classes must never pay for it (#748).
A matching failure on an aspect is swallowed (aspects used to be skipped before matching); on other classes it is rethrown.
