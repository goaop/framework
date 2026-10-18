# src/Proxy — Proxy generation

## Proxy generators
- ClassProxyGenerator — trait-based proxy for regular classes
  - Constructor takes $traitName (FooOriginalTrait FQCN) as 2nd arg
  - Always emits `use $traitName` (even for introduction-only aspects)
  - Parent and interfaces from reflection are always rooted (`\`); only the OriginalTrait name is a deliberate short
    relative name (#759)
- FunctionProxyGenerator — function wrappers
- TraitProxyGenerator — trait proxies
- EnumProxyGenerator — enum proxies (trait extraction + case re-declaration)

## Imports in generated code (issue #668)
- Generated code stays clean and readable: framework and aspect classes are referenced through short `use` aliases
  (`Interceptor::before(The::aspect(LoggingAspect::class)->...)`); NEVER fall back to fully-qualified names
- ProxyImports (src/Proxy/Generator/) is the single source of these aliases, one instance per generated file:
  - reserves the original file imports (passed by WeavingTransformer as the generators' `$originalImports`), the class
    short name, the original trait name and every unqualified/qualified name used in the original class AST
  - `import(FQCN)` returns the alias to emit: the short name when free, else `Aop<Short>` for `Go\Aop\*` classes or
    `<NamespaceSegment><Short>` for others (aspects sharing a short name), then a numeric suffix; reuses the alias of
    an original import of the same class
  - generators register all their imports up front (stable order = output identical to the pre-alias generators when
    nothing collides), pass the instance to InterceptorListGenerator and the property generators, use the aliases
    in body name nodes / `@var` docblocks, and finally add `getUses()` (generated imports first, original ones after)

## Generated bodies are AST, never strings (issue #750)
- Intercepted method/function bodies and `__staticInitialization`/`__initialization` are built as nodes:
  Part/JoinPointStatementsGenerator (`static $__joinPoint` + `@var` doc, `__invoke` Return_/Expression),
  InterceptorListGenerator::getNode(), FunctionCallArgumentListGenerator::getArgs(); set on `MethodGenerator::$stmts` /
  `FunctionGenerator::$stmts`. NEVER build a body string for `->body` in generators (it costs a parse + print per body);
  the `body` string API stays for tests/callers only
- Output must stay byte-identical to what printing the parsed body produced: set `kind` attributes where the printer
  reads them (short arrays), mirror parsed node shapes (Name('self'), FullyQualified function names)
- ClassProxyGenerator::getJoinpointInvocationStatements() is shared by Class/Trait/Enum generators; they differ only
  in createOriginalMethodCallable() (trait/enum: always `<method>OriginalAlias`, class: alias or `parent::m(...)`)
- Method lookups: ClassProxyGenerator::indexMethods() (name → method) is built once per woven class by
  WeavingTransformer and passed to the generators; parser-reflection hasMethod()/getMethod() are linear scans
- GeneratedCodePrinter recognizes InterceptorInjector/Interceptor factory calls by method name too, so aliased
  names keep the multi-line layout

## Proxy parts (src/Proxy/Part/)
- InterceptedMethodGenerator — wraps a method with join-point dispatch
  - Calls `$this->__constructOriginalAlias()` when constructor is in trait, `parent::__construct()` otherwise
- InterceptedPropertyGenerator — re-declares properties with native get/set hooks → ClassFieldAccess

## Generators (src/Proxy/Generator/)
- ClassGenerator — builds proxy class AST
  - addTraitAlias() registers trait + alias in single `use { ... }` block; deduplicates traits
- AttributeGroupsGenerator — copies PHP 8 attributes from reflection to proxy AST (preserves named args)
  - arguments cloned by one static traverser (CloningVisitor + ResolvedNameQualifyingVisitor); no-arg attributes skip it
- TypeGenerator — converts ReflectionType to AST nodes or phpDoc strings
  - fromResolvedAstNode(): parser-reflection type nodes → type, same result as TypeExpressionResolver(null, null) +
    fromReflectionType() (used by MethodGenerator/ParameterGenerator), without the reflection round trip
  - renderAstTypeForPhpDoc() (AST node available) / renderTypeForPhpDoc() (fallback) for @var generic on $__joinPoint

## PHP compat: Readonly classes (8.2+)
- Generated trait drops `readonly` (traits can't be readonly in PHP)
- Proxy class preserves `readonly`
- Per-method function-scoped static $__joinPoint (not class property) — allowed in readonly classes
- Readonly properties excluded from access() interception (can't have hooks)

## PHP compat: Property hooks (8.4+)
- Intercepted properties: moved from trait to proxy, emitted with get/set hooks dispatching through ClassFieldAccess
- Hooks pass values by value (`read()`/`write()`); array properties get only `&get` → `readByReference()`. No default → `isset($this->p) || $__joinPoint->getField()->isInitialized($this)` guard (built in AbstractInterceptedPropertyGenerator)
- Woven trait: property declarations neutralized (avoid conflicts, preserve line numbers)
- Readonly properties and properties with existing hooks: skipped for access() interception

## PHP compat: Enum proxies
- Original enum → trait (cases stripped, backed type removed, enum→trait)
- Proxy enum re-uses trait, re-declares all cases, per-method static $__joinPoint
- Built-in enum methods (cases/from/tryFrom): NEVER intercepted (PHP-synthesised, can't alias via trait use)
- UnitEnum/BackedEnum: NEVER in proxy implements (PHP auto-applies; explicit listing resolves as Ns\UnitEnum → fatal error)
