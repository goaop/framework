# src/Aop — Aspect-Oriented Programming core

## Interfaces (src/Aop/Intercept/)

### Joinpoint hierarchy
Joinpoint → Invocation
Joinpoint → ClassJoinpoint
Invocation → MethodInvocation
Invocation → ConstructorInvocation
Invocation → FunctionInvocation
ClassJoinpoint → FieldAccess
ClassJoinpoint → MethodInvocation
ClassJoinpoint → ConstructorInvocation

### Generics (PHPStan type-awareness)
| Interface               | Generic                  | T=                         | V=             |
|-------------------------|--------------------------|----------------------------|----------------|
| MethodInvocation        | `<T of object, V=mixed>` | class holding method       | return type    |
| DynamicMethodInvocation | `<T, V>`                 | getThis()→T (covariant)    | return type    |
| StaticMethodInvocation  | `<T, V>`                 | getThis()→null (covariant) | return type    |
| FunctionInvocation      | `<V=mixed>`              | —                          | return type    |
| FieldAccess             | `<T of object, V=mixed>` | class holding property     | property type  |
| ConstructorInvocation   | `<T of object>`          | class being constructed    | —              |

Proxy generators use TypeGenerator::renderTypeForPhpDoc() to emit V as 2nd generic arg in per-method @var annotations — gives IDE/PHPStan full type-awareness on $__joinPoint->__invoke().

## Implementations (src/Aop/Framework/)
| Class                             | Implements              | Key behavior                                                                                                           |
|-----------------------------------|-------------------------|------------------------------------------------------------------------------------------------------------------------|
| AbstractMethodInvocation          | MethodInvocation        | Base; protected readonly Closure $closureToCall (FCC); TRAIT_ALIAS_SUFFIX='OriginalAlias'; keeps method reflection           |
| DynamicTraitAliasMethodInvocation | DynamicMethodInvocation | receives $this->mOriginalAlias(...) or parent::m(...); proceed() via ReflectionMethod::invokeArgs (handles by-ref correctly) |
| StaticTraitAliasMethodInvocation  | StaticMethodInvocation  | FCC shim: static fn(array $args) => forward_static_call_array(...); bindTo(null, $scope) per call                      |
| ReflectionConstructorInvocation   | ConstructorInvocation   | newInstanceWithoutConstructor() then call constructor (requires INTERCEPT_INITIALIZATIONS feature)                     |
| ReflectionFunctionInvocation      | FunctionInvocation      | receives FCC to global fn (e.g. \strlen(...) with leading \ to avoid recursive proxy call)                             |
| ClassFieldAccess                  | FieldAccess             | Property interception via native get/set hooks on proxied properties                                                   |
| StaticInitializationJoinpoint     | ClassJoinpoint          | Fired once after proxy class loaded via injectJoinPoints()                                                             |

## Advice wiring (src/Aop/Framework/)
- The — proxy-code accessor: aspect(X::class) fetches aspect from container; advice('advisorId') resolves container-backed closure advice (unwraps Advisor/AbstractInterceptor to raw Closure)
- Interceptor — @internal factory facade with TWO construction modes: before()/after()/around()/afterThrowing(class-string<Aspect>|Closure, ?string $methodName=null, int $order=0, string $expression=''). With aspect class + method name it returns a native PHP 8.4 lazy proxy (via Go\Core\NativeLazyProxy::create) — interceptor construction, The::aspect() resolution and FCC creation all defer until first real use (invocation/ordering), so unmatched advices never instantiate their aspect; ONLY compiled advisor cache files use this lazy form. A ready Closure constructs eagerly, and generated PROXY classes deliberately use the eager `The::aspect(X::class)->method(...)` FCC form (InterceptorListGenerator): the proxy method/hook is already executing, so the interceptor is needed right now and a lazy detour would be pure overhead; The::advice('<id>') stays eager too. Free to change between releases
- GeneratedInterceptor — internal descriptor built by AbstractJoinpoint::flatAndSortAdvices() via fromAdvice(); usesContainerAdvice=true when advice closure isn't scoped to an Aspect class
- AdviceTypeEnum — Advice::getType() kind + sorting priority (before → after/afterThrowing → around → introduction); replaced AdviceBefore/AdviceAfter/AdviceAround marker interfaces
- Advice methods MUST be public (FCC calls them on the aspect instance from generated code)

## Pointcuts (src/Aop/Pointcut/)
- LALR grammar: PointcutGrammar (@internal, no ctor deps), PointcutParser, PointcutLexer, PointcutParseTable
- Combinators: AndPointcut, OrPointcut, NotPointcut, NamePointcut, AttributePointcut, ClassInheritancePointcut, MatchInheritedPointcut, ModifierPointcut, ReturnTypePointcut, TruePointcut
- PointcutReference (@internal; ctor takes pointcut id only, container resolved lazily from AspectKernel::getInstance()), ClassMemberReference
- ModifierPointcut is @internal final readonly: ctor (andMask, orMask, notMask); andMatch/orMatch/notMatch are withers returning new self
- Grammar/pointcut classes marked @internal are free to break between releases — no CHANGELOG entries for their signature changes

## Compilable (src/Aop/Compilable.php, @internal)
- compileToPhp(): PhpParser\Node\Expr — emit a nested static-constructor expression recreating the instance for the compiled advisor cache; rationale (opcache caching/optimization/inlining, no serialize, var_export can't express Closures) documented on the interface
- The BASE `Pointcut`, `Advice` and `Advisor` interfaces extend Compilable, so every implementation compiles by construction. Children: all concrete pointcuts (And/Or/Not/Name/Attribute/ClassInheritance/MatchInherited/True/Modifier/ReturnType/PointcutReference), GenericPointcutAdvisor, LazyPointcutAdvisor (compiles to a resolved GenericPointcutAdvisor), TraitIntroductionInfo, AbstractInterceptor (emits pure-static-data Interceptor::before(Aspect::class, 'method', ...) facade calls — no FCC in cache files)
- Emission rules: resolved private state as ctor args; trailing declared defaults omitted; named args when earlier defaults skipped; args that are class names by construction (attribute class, parent class, trait/interface, aspect class) always emit `Fqcn::class` (never class_exists checks — compilation may run mid-classload inside the autoloader); patterns/expressions stay string literals; global (single-part) names never get a `use` statement (cache files are namespace-less — a non-compound use raises an engine warning), they stay `\Name::class` inline
- Go\Core\Cache\NotCompilableException is now a LIMITED subset: an advice closure not scoped to an Aspect class (foreign interceptor subclass included), or a userland implementation whose compileToPhp() deliberately throws; it PROPAGATES out of the cache writer (no catch + warning) — such aspects cannot run with the advisor cache enabled

## Attributes (src/Lang/Attribute/)
- Advice: #[Before], #[After], #[Around], #[AfterThrowing]
- Declaration: #[Aspect], #[Pointcut], #[DeclareParents]
- Base: AbstractAttribute, AbstractInterceptor, Interceptor (interface)

## Features (src/Aop/Features.php)
Interface with bitmask constants:
- INTERCEPT_FUNCTIONS=1, INTERCEPT_INITIALIZATIONS=2, INTERCEPT_INCLUDES=4
- PREBUILT_CACHE=64 — assume cache already prepared, skip freshness checks
