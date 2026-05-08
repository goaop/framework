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

## Pointcuts (src/Aop/Pointcut/)
- LALR grammar: PointcutGrammar, PointcutParser, PointcutLexer, PointcutParseTable
- Combinators: AndPointcut, OrPointcut, NotPointcut, NamePointcut, AttributePointcut, ClassInheritancePointcut, MatchInheritedPointcut, ModifierPointcut, ReturnTypePointcut, MagicMethodDynamicPointcut, TruePointcut
- PointcutReference, ClassMemberReference

## Attributes (src/Lang/Attribute/)
- Advice: #[Before], #[After], #[Around], #[AfterThrowing]
- Declaration: #[Aspect], #[Pointcut], #[DeclareError], #[DeclareParents]
- Base: AbstractAttribute, AbstractInterceptor, Interceptor (interface)

## Features (src/Aop/Features.php)
Interface with bitmask constants:
- INTERCEPT_FUNCTIONS=1, INTERCEPT_INITIALIZATIONS=2, INTERCEPT_INCLUDES=4
- PREBUILT_CACHE=64 — assume cache already prepared, skip freshness checks
- PARAMETER_WIDENING=128 — enable parameter widening for PHP>=7.2
