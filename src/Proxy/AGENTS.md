# src/Proxy — Proxy generation

## Proxy generators
- ClassProxyGenerator — trait-based proxy for regular classes
  - Constructor takes $traitName (FooOriginalTrait FQCN) as 2nd arg
  - Always emits `use $traitName` (even for introduction-only aspects)
  - Parent, interfaces (original, introduced, `Go\Aop\Proxy`, `*InitializationAware`) and introduced traits go through
    ProxyImports like every other class (#826, was rooted for #759: a bare global name resolved in the proxy
    namespace); no technical reason to root them, `use` imports apply to the class header. The OriginalTrait name is a
    deliberate short relative name (same namespace, reserved)
- FunctionProxyGenerator — function wrappers
- TraitProxyGenerator — trait proxies
- EnumProxyGenerator — enum proxies (trait extraction + case re-declaration)

## Imports in generated code (issues #668, #826)
- Generated code stays clean and readable: EVERY class is referenced through a short `use` alias — framework and aspect
  classes (`Interceptor::before(The::aspect(LoggingAspect::class)->...)`), the class header, signature types (params,
  returns, property types incl. nullable/union/intersection/DNF), attribute names and class references in copied
  defaults/attribute arguments (`Foo::BAR`, `new Foo()`), `@var` generics; NEVER fall back to fully-qualified names.
  Kept as they are: builtin types, `self`/`static`/`parent`, function and constant names (fully qualified when the
  NameResolver resolved them, e.g. `\array_slice`), unresolved names (`PHP_INT_MAX`, namespace fallback)
- ProxyImports (src/Proxy/Generator/) is the single source of these aliases, one instance per generated file:
  - binds short names (alias → class/namespace, unknown = reserved): the original file imports (passed by
    WeavingTransformer as the generators' `$originalImports`), the class short name (→ the class), the original trait
    name and every unqualified/qualified name used in the original class AST (first segment → its `resolvedName`
    prefix; no resolvedName → unknown); conflicting bindings become unknown
  - `import(FQCN)` returns the alias to emit: the original import's alias of the class; else the short name when it is
    unbound or bound to that very class (a class of the file namespace then needs no `use`: getUses() skips it, the
    proxied class itself must never be imported); else `Aop<Short>` for `Go\Aop\*` classes, `Global<Short>` for
    global classes or `<NamespaceSegment><Short>` for others, then a numeric suffix. Lookups are case-insensitive
  - `importNamesIn(nodes)` deep-clones copied expressions (parameter/property defaults, attribute arguments) through
    ImportedNameVisitor: resolved names → fully qualified, class positions (ClassConstFetch/StaticCall/
    StaticPropertyFetch/New_/Instanceof_ class, Attribute name, Param/NullableType/Union/Intersection/Catch types,
    closure return types) → alias. Defaults are copied resolved, not as written: an inherited method/property is
    declared in another file whose imports differ from the woven file's
  - TypeGenerator::withImportedNames(imports) maps the class names of a type, renderAstTypeForPhpDoc()/
    renderTypeForPhpDoc() take the imports for `@var` generics; MethodGenerator/FunctionGenerator/ParameterGenerator::
    fromReflection(), AttributeGroupsGenerator::fromReflector()/fromAttributeGroupNodes()/fromReflectionAttributes()
    and InterceptedMethodGenerator take an optional `?ProxyImports` (null = fully qualified output, standalone use)
  - generators register the framework/aspect imports up front (stable aliases), then class attributes, header,
    properties, method signatures and bodies in output order; property nodes are built eagerly (ClassGenerator takes
    built Property nodes too) and FunctionProxyGenerator adds the uses after generating the functions, because
    getUses() (generated imports first, original ones after) must come after the last import() call

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
  - class-like names (parent, interfaces, traits; EnumGenerator interfaces alike): rooted (`\X`) or multi-segment →
    fully qualified, bare → relative (an alias of the file imports, or the OriginalTrait)
- AttributeGroupsGenerator — copies PHP 8 attributes from reflection to proxy AST (preserves named args)
  - arguments cloned by one static traverser (CloningVisitor + ResolvedNameQualifyingVisitor); no-arg attributes skip it
- TypeGenerator — converts ReflectionType to AST nodes or phpDoc strings
  - fromResolvedAstNode(): parser-reflection type nodes → type, same result as TypeExpressionResolver(null, null) +
    fromReflectionType() (used by MethodGenerator/ParameterGenerator), without the reflection round trip
  - renderAstTypeForPhpDoc() (AST node available) / renderTypeForPhpDoc() (fallback) for @var generic on $__joinPoint

## PHP compat: Readonly classes (8.2+)
- Generated trait drops `readonly` (traits can't be readonly in PHP); TraitConverter puts `readonly` on each property and
  promoted parameter instead, a readonly class can't use a trait with non-readonly properties (#792)
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
- Proxy enum re-uses trait, re-declares all cases with their attributes (AttributeGroupsGenerator, imported names) and doc
  comments (#794; includes PHP 8.6 `#[\Override]` on a case), per-method static $__joinPoint
- Built-in enum methods (cases/from/tryFrom): NEVER intercepted (PHP-synthesised, can't alias via trait use)
- UnitEnum/BackedEnum: NEVER in proxy implements (PHP auto-applies; explicit listing resolves as Ns\UnitEnum → fatal error)
