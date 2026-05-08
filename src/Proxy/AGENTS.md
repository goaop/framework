# src/Proxy — Proxy generation

## Proxy generators
- ClassProxyGenerator — trait-based proxy for regular classes
  - Constructor takes $traitName (Foo__AopProxied FQCN) as 2nd arg
  - Always emits `use $traitName` (even for introduction-only aspects)
- FunctionProxyGenerator — function wrappers
- TraitProxyGenerator — trait proxies
- EnumProxyGenerator — enum proxies (trait extraction + case re-declaration)

## Proxy parts (src/Proxy/Part/)
- InterceptedMethodGenerator — wraps a method with join-point dispatch
- InterceptedConstructorGenerator — wraps constructor
  - Calls `$this->__aop____construct()` when constructor is in trait, `parent::__construct()` otherwise
- InterceptedPropertyGenerator — re-declares properties with native get/set hooks → ClassFieldAccess

## Generators (src/Proxy/Generator/)
- ClassGenerator — builds proxy class AST
  - addTraitAlias() registers trait + alias in single `use { ... }` block; deduplicates traits
- AttributeGroupsGenerator — copies PHP 8 attributes from reflection to proxy AST (preserves named args)
- TypeGenerator — converts ReflectionType to AST nodes or phpDoc strings
  - renderTypeForPhpDoc() used by all proxy generators for @var generic on $__joinPoint

## PHP compat: Readonly classes (8.2+)
- Generated trait drops `readonly` (traits can't be readonly in PHP)
- Proxy class preserves `readonly`
- Per-method function-scoped static $__joinPoint (not class property) — allowed in readonly classes
- Readonly properties excluded from access() interception (can't have hooks)

## PHP compat: Property hooks (8.4+)
- Intercepted properties: moved from trait to proxy, emitted with get/set hooks dispatching through ClassFieldAccess
- Woven trait: property declarations neutralized (avoid conflicts, preserve line numbers)
- Readonly properties and properties with existing hooks: skipped for access() interception

## PHP compat: Enum proxies
- Original enum → trait (cases stripped, backed type removed, enum→trait)
- Proxy enum re-uses trait, re-declares all cases, per-method static $__joinPoint
- Built-in enum methods (cases/from/tryFrom): NEVER intercepted (PHP-synthesised, can't alias via trait use)
- UnitEnum/BackedEnum: NEVER in proxy implements (PHP auto-applies; explicit listing resolves as Ns\UnitEnum → fatal error)
