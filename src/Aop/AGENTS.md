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
| AbstractMethodInvocation          | MethodInvocation        | Base; protected readonly Closure $closureToCall (FCC); TRAIT_ALIAS_PREFIX='__aop__'; keeps method reflection           |
| DynamicTraitAliasMethodInvocation | DynamicMethodInvocation | receives $this->__aop__m(...) or parent::m(...); proceed() via ReflectionMethod::invokeArgs (handles by-ref correctly) |
| StaticTraitAliasMethodInvocation  | StaticMethodInvocation  | FCC shim: static fn(array $args) => forward_static_call_array(...); bindTo(null, $scope) per call                      |
| ReflectionConstructorInvocation   | ConstructorInvocation   | newInstanceWithoutConstructor() then call constructor (requires INTERCEPT_INITIALIZATIONS feature)                     |
| ReflectionFunctionInvocation      | FunctionInvocation      | receives FCC to global fn (e.g. \strlen(...) with leading \ to avoid recursive proxy call)                             |
| ClassFieldAccess                  | FieldAccess             | Property interception via native get/set hooks on proxied properties                                                   |
| StaticInitializationJoinpoint     | ClassJoinpoint          | Fired once after proxy class loaded via injectJoinPoints()                                                             |

## Advice wiring (src/Aop/Framework/)
- The — proxy-code accessor: aspect(X::class) fetches aspect from container; advice('advisorId') resolves container-backed closure advice (unwraps Advisor/AbstractInterceptor to raw Closure)
- Interceptor — @internal factory facade for generated code and compiled advisor caches: before()/after()/around()/afterThrowing(Closure, int $order=0, string $expression=''); free to change between releases
- GeneratedInterceptor — internal descriptor built by AbstractJoinpoint::flatAndSortAdvices() via fromAdvice(); usesContainerAdvice=true when advice closure isn't scoped to an Aspect class
- AdviceTypeEnum — Advice::getType() kind + sorting priority (before → after/afterThrowing → around → introduction); replaced AdviceBefore/AdviceAfter/AdviceAround marker interfaces
- Advice methods MUST be public (FCC calls them on the aspect instance from generated code)

## Pointcuts (src/Aop/Pointcut/)
- LALR grammar: PointcutGrammar (@internal, no ctor deps), PointcutParser, PointcutLexer, PointcutParseTable
- Combinators: AndPointcut, OrPointcut, NotPointcut, NamePointcut, AttributePointcut, ClassInheritancePointcut, MatchInheritedPointcut, ModifierPointcut, ReturnTypePointcut, TruePointcut
- PointcutReference (@internal; ctor takes pointcut id only, container resolved lazily from AspectKernel::getInstance()), ClassMemberReference
- ModifierPointcut is @internal final readonly: ctor (andMask, orMask, notMask); andMatch/orMatch/notMatch are withers returning new self
- Grammar/pointcut classes marked @internal are free to break between releases — no CHANGELOG entries for their signature changes

## CompilableToPhp (src/Aop/CompilableToPhp.php, @internal)
- compileToPhp(): PhpParser\Node\Expr — emit a nested static-constructor expression recreating the instance for the compiled advisor cache
- Implemented by every concrete Pointcut, GenericPointcutAdvisor, TraitIntroductionInfo, AbstractInterceptor (emits Interceptor::before/after/around/afterThrowing facade calls)
- Emission rules: resolved private state as ctor args; trailing declared defaults omitted; named args when earlier defaults skipped; args that are class names by construction (attribute class, parent class, trait/interface, aspect class) always emit `Fqcn::class` (never class_exists checks — compilation may run mid-classload inside the autoloader); patterns/expressions stay string literals
- Non-compilable nested item (custom Advisor/Pointcut/Advice or foreign interceptor subclass) → throw Go\Core\NotCompilableException; the loader then skips caching that aspect with an E_USER_WARNING

## Attributes (src/Lang/Attribute/)
- Advice: #[Before], #[After], #[Around], #[AfterThrowing]
- Declaration: #[Aspect], #[Pointcut], #[DeclareParents]
- Base: AbstractAttribute, AbstractInterceptor, Interceptor (interface)

## Features (src/Aop/Features.php)
Interface with bitmask constants:
- INTERCEPT_FUNCTIONS=1, INTERCEPT_INITIALIZATIONS=2, INTERCEPT_INCLUDES=4
- PREBUILT_CACHE=64 — assume cache already prepared, skip freshness checks
