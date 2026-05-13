# src/Instrument — AOP interception pipeline

## Init flow
1. AspectKernel::init() — singleton, registers stream filter, builds transformers, calls configureAop()
2. SourceTransformingLoader::register() — PHP stream filter (php://filter/read=go.source.transforming.loader/resource=... protocol)
3. AopComposerLoader::init() — hooks Composer autoloader → redirects through stream filter
4. CachingTransformer — outer; cache miss → inner chain → write cache

## Transformer chain (order matters)
Applied per loaded file. Each returns TransformerResultEnum: RESULT_TRANSFORMED|RESULT_ABSTAIN|RESULT_ABORTED.

1. ConstructorExecutionTransformer — new expressions (works only if INTERCEPT_INITIALIZATIONS enabled)
2. FilterInjectorTransformer — include/require (works only if INTERCEPT_INCLUDES enabled)
3. WeavingTransformer — main; AdviceMatcher + CachedAspectLoader → proxy generators
4. MagicConstantTransformer — `__FILE__`/`__DIR__` → original paths

## Trait-based proxy engine (4.0)
WeavingTransformer converts original class to trait + proxy class. Two generated files for class Ns\Foo:

### Woven file (replaces original in php stream filtering)
```php
trait Foo__AopProxied { /* original methods verbatim */ }
include_once AOP_CACHE_DIR . '/Foo.php';
```

### Proxy file (loaded by include_once)
```php
class Foo extends OriginalParent implements OriginalInterfaces, \Go\Aop\Proxy
{
    use \Ns\Foo__AopProxied {
        \Ns\Foo__AopProxied::interceptedMethod as private __aop__interceptedMethod;
    }
    public function interceptedMethod(ArgType $arg): ReturnType {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, ReturnType> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class, 'interceptedMethod', [...], $this->__aop__interceptedMethod(...)
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
}
```

### Key invariants
- Proxy re-inherits parent+interfaces via reflection (not from woven source)
- self:: in trait body → proxy class (no rewrite needed)
- Private methods interceptable (impossible with old extend-based engine)
- FCC 4th arg to InterceptorInjector:
  - `$this->__aop__m(...)` — own dynamic methods
  - `self::__aop__m(...)` — own static methods
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
