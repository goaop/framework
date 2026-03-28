# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Go! AOP Framework** — an Aspect-Oriented Programming (AOP) framework for PHP 8.4+. It intercepts PHP class/method/function execution transparently by transforming source code at load time via a custom PHP stream wrapper, without requiring PECL extensions, annotations at runtime, or eval.

Package: `goaop/framework` | Namespace root: `Go\` | PHP: `^8.4.0`

## Commands

```bash
# Install dependencies
composer install

# Run full test suite
./vendor/bin/phpunit

# Run a single test file
./vendor/bin/phpunit tests/Go/Core/ContainerTest.php

# Run a single test method
./vendor/bin/phpunit --filter testMethodName tests/Go/Core/ContainerTest.php

# Static analysis (PHPStan level 10, src/ only)
./vendor/bin/phpstan analyze --memory-limit=512M

# CLI debugging tools
./bin/aspect debug:advisors [class]
./bin/aspect debug:pointcuts [expression]
```

## Architecture

The framework works by intercepting PHP's class loading pipeline. When a class is loaded, the stream wrapper transforms its source code to inject interception hooks, then stores the result in a cache directory. The transformed class contains calls into the advisor chain for each matched join point.

### Initialization flow

1. **`AspectKernel::init()`** (`src/Core/AspectKernel.php`) — singleton, registers stream wrapper, builds transformer chain, calls `configureAop()` where users register aspects
2. **`SourceTransformingLoader::register()`** (`src/Instrument/ClassLoading/SourceTransformingLoader.php`) — PHP stream wrapper that intercepts `include`/`require` via the `go-aop-php://` protocol
3. **`AopComposerLoader::init()`** (`src/Instrument/ClassLoading/AopComposerLoader.php`) — hooks into Composer's autoloader to redirect loads through the stream wrapper
4. **`CachingTransformer`** — outer transformer that manages cache; on cache miss, invokes the inner transformers and writes the result

### Transformer chain (inner, registered in `AspectKernel::registerTransformers()`)

Applied in order for each loaded file:
- `ConstructorExecutionTransformer` — transforms `new` expressions (when `INTERCEPT_INITIALIZATIONS` feature enabled)
- `FilterInjectorTransformer` — wraps `include`/`require` (when `INTERCEPT_INCLUDES` enabled)
- `WeavingTransformer` — main transformer; uses `AdviceMatcher` to find applicable advices and `CachedAspectLoader` for aspect metadata, then delegates to proxy generators
- `MagicConstantTransformer` — rewrites `__FILE__`/`__DIR__` so they resolve to the original file, not the cached proxy

> **Note:** `SelfValueTransformer` was removed in 4.0 — `self::` in traits resolves to the using class naturally.

Each transformer returns `TransformerResultEnum`: `RESULT_TRANSFORMED`, `RESULT_ABSTAIN`, or `RESULT_ABORTED`.

### Trait-based proxy engine (4.0)

`WeavingTransformer` converts the original class to a **PHP trait** and writes a proxy class that uses it. The two generated files for a class `Ns\Foo` are:

**Woven file** (replaces the original in the load stream):
```php
// Original class body converted to a trait; final/abstract/extends/implements stripped
trait Foo__AopProxied { /* original methods verbatim */ }
include_once AOP_CACHE_DIR . '/_proxies/.../Foo.php';
```

**Proxy file** (loaded by the `include_once` above):
```php
class Foo extends OriginalParent implements OriginalInterfaces, \Go\Aop\Proxy
{
    use \Ns\Foo__AopProxied {
        \Ns\Foo__AopProxied::interceptedMethod as private __aop__interceptedMethod;
        // ... one alias per intercepted method (including private ones)
    }
    private static array $__joinPoints = [];

    public function interceptedMethod(...) {
        return self::$__joinPoints['method:interceptedMethod']->__invoke($this, [...]);
    }
    // ... one override per intercepted method
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(Foo::class, [...]);
```

Key properties of this engine:
- The proxy class **re-inherits** the original parent and interfaces (read from reflection, not from the woven source).
- `self::` in the trait body resolves to `Foo` (the proxy class) — no rewriting needed.
- **Private methods can be intercepted** (impossible with the old extend-based engine).
- Proceed path uses a pre-bound `Closure::bind($fn, null, Foo::class)` stored once per join point — zero per-call reflection.

### Proxy generation (`src/Proxy/`)

- `ClassProxyGenerator` — generates the trait-based proxy class for a regular class
  - Takes `$traitName` (the `Foo__AopProxied` FQCN) as second constructor arg
  - Always emits `use $traitName` even when no methods are intercepted (introduction-only aspects)
  - Adds `__construct as private __aop____construct` alias when the class defines its own constructor and properties are intercepted
- `FunctionProxyGenerator` — generates function wrappers
- `TraitProxyGenerator` — generates trait proxies (uses old `adjustOriginalClass` path)
- `src/Proxy/Part/` — individual code-generation components:
  - `InterceptedMethodGenerator` — wraps a single method with join-point delegation
  - `InterceptedConstructorGenerator` — wraps constructor; uses `self::class` (not `parent::class`) for `Closure::bindTo` scope; calls `$this->__aop____construct()` when constructor is in the trait
  - `JoinPointPropertyGenerator` — the `private static array $__joinPoints` property
  - `PropertyInterceptionTrait` — `__get`/`__set`/`__isset`/`__unset` magic that routes through `ClassFieldAccess` join points
- `src/Proxy/Generator/` — low-level AST generators:
  - `ClassGenerator` — builds the proxy class AST node; `addTraitAlias()` registers both the trait and an alias in a single `use { ... }` block; deduplicates traits
  - `AttributeGroupsGenerator` — copies PHP 8 attributes from reflection to proxy AST, preserving named arguments

### AOP core (`src/Aop/`)

- `src/Aop/Intercept/` — interfaces: `Joinpoint`, `Invocation`, `MethodInvocation`, `ConstructorInvocation`, `FunctionInvocation`, `FieldAccess`
- `src/Aop/Framework/` — concrete invocation implementations:
  - `AbstractMethodInvocation` — base class; holds `protected Closure $closureToCall` (set in each subclass constructor via `Closure::bind`); `TRAIT_ALIAS_PREFIX = '__aop__'` constant; manages recursive/cross-call stack frames
  - `DynamicTraitAliasMethodInvocation` — instance-method invocation; builds `Closure::bind(fn($i, $a) => $i->__aop__method(...$a), null, $class)` once at construction; `proceed()` calls `($this->closureToCall)($this->instance, $this->arguments)`
  - `StaticTraitAliasMethodInvocation` — static-method invocation; builds `Closure::bind(fn($c, $a) => $c::__aop__method(...$a), null, $class)` once at construction; `proceed()` calls `($this->closureToCall)($this->scope, $this->arguments)`
  - `ReflectionConstructorInvocation` — constructor interception (used with `INTERCEPT_INITIALIZATIONS`); creates instance via `ReflectionClass::newInstanceWithoutConstructor()` then calls constructor
  - `ReflectionFunctionInvocation` — function interception; `proceed()` calls `$this->reflectionFunction->invokeArgs($this->arguments)`
  - `ClassFieldAccess` — property interception join point; used via `PropertyInterceptionTrait`
  - `StaticInitializationJoinpoint` — fired once after proxy class is loaded via `injectJoinPoints()`
- `src/Aop/Pointcut/` — LALR pointcut grammar (`PointcutGrammar`, `PointcutParser`, `PointcutLexer`, `PointcutParseTable`) and pointcut combinators (`AndPointcut`, `OrPointcut`, `NotPointcut`, `NamePointcut`, `AttributePointcut`, etc.)
- `src/Lang/Attribute/` — PHP 8 attributes for declaring aspects and advice: `#[Aspect]`, `#[Before]`, `#[After]`, `#[Around]`, `#[AfterThrowing]`, `#[Pointcut]`, `#[DeclareError]`, `#[DeclareParents]`
- `src/Aop/Features.php` — bitmask enum for optional features (`INTERCEPT_FUNCTIONS`, `INTERCEPT_INITIALIZATIONS`, `INTERCEPT_INCLUDES`)

### Container and aspect loading (`src/Core/`)

- `Container.php` — DI container with `add()` (by class-string or key), `getService()`, `addLazyService()` (Closure), and automatic tagging by interface
- `AspectLoader` / `CachedAspectLoader` — scan aspect classes for pointcut/advice attributes and produce `Advisor` instances
- `AttributeAspectLoaderExtension` — handles PHP 8 attribute-based aspect definitions
- `AdviceMatcher` — given a class reflector, returns the set of applicable advisors keyed by join point; scans `IS_PUBLIC | IS_PROTECTED | IS_PRIVATE` methods (private methods from parent classes are excluded)

### Bridge

`src/Bridge/Doctrine/MetadataLoadInterceptor.php` — workaround for Doctrine ORM entity weaving (Doctrine loads metadata before the kernel can intercept classes).

## PHP version support and known limitations

The framework supports PHP 8.4+ and handles most modern PHP syntax transparently. The following constructs have documented limitations or are intentionally excluded:

### Enums (PHP 8.1+) — woven via trait extraction

Enums are supported by `WeavingTransformer` using the same trait-extraction approach as classes, with adjustments for enum constraints:
- The original enum body is converted to a **trait** (cases stripped, backed type removed, `enum` → `trait`)
- A proxy **enum** is generated that re-uses the trait, re-declares all cases, and adds per-method `static $__joinPoint` dispatch — using `EnumProxyGenerator`
- Enums **cannot** have properties (static or instance), so the `$__joinPoints` class property pattern used by `ClassProxyGenerator` does not apply; per-method static variables are used instead (same as `TraitProxyGenerator`)
- Built-in enum methods (`cases`, `from`, `tryFrom`) are **never** intercepted — they are synthesised by PHP and cannot be aliased via trait use
- Built-in PHP enum interfaces (`UnitEnum`, `BackedEnum`) are **never** listed in the proxy's `implements` clause — PHP applies them automatically, and listing them explicitly in a namespaced file resolves them as `Ns\UnitEnum` instead of the global `\UnitEnum`, causing a fatal error

### Readonly classes (PHP 8.2+) — proxy is not readonly

When a `readonly class` is woven, the generated trait drops the `readonly` modifier (traits cannot be readonly). The proxy class is also **not** readonly, because it requires the `private static array $__joinPoints` property, which PHP forbids in readonly classes. The proxy class therefore relaxes the readonly constraint. Readonly *properties* inside the class continue to work correctly.

### `#[\Override]` on intercepted methods (PHP 8.3+) — attribute stripped from trait

When a method marked `#[\Override]` is intercepted (i.e., aliased as `__aop__methodName` in the proxy's trait-use block), PHP would copy the `#[\Override]` attribute to the alias. Since the alias name has no parent method to override, PHP would raise a fatal error. `WeavingTransformer::convertClassToTrait()` therefore strips `#[\Override]` from the method body in the generated trait for every intercepted method. The attribute is **preserved on the proxy's override method**, where it is valid (the proxy extends the same parent).

### PHP 8.4 property hooks — pass-through only

Property hooks are included verbatim in the generated trait body; they are not a separate join-point type. You can intercept the owning method (if any) but you cannot write a pointcut that targets a hook `get`/`set` clause directly. `ClassFieldAccess` property interception and hooked properties on the same property are not supported simultaneously.

### Woven trait file line numbers must match the original source (XDebug compatibility)

The **woven file** (the trait that replaces the original class/enum body) **must** preserve the original source line numbers. This is required for XDebug breakpoints to map correctly: a breakpoint placed at a method in the original source file must land on the same line number in the woven trait file, because that is the file XDebug steps through when executing the real method body.

`WeavingTransformer` achieves this via token-level surgery on the original source:
- For classes: `convertClassToTrait()` replaces the `class` keyword and strips modifiers/extends/implements, but keeps all other tokens (including blank lines) in place.
- For enums: `convertEnumToTrait()` must **replace removed tokens** (case declarations, backed type, implements clause) with an **equal number of newlines** so that methods remain at their original line positions. Removing tokens without replacement shifts subsequent lines upward.

The proxy file (generated by `ClassProxyGenerator`/`EnumProxyGenerator`) is a thin dispatch wrapper and does **not** need to match original line numbers. Debuggers will step through the woven trait for the real method bodies.

### Aspects themselves — never woven

Classes that implement `\Go\Aop\Aspect` are unconditionally skipped by `WeavingTransformer`. Aspects cannot weave themselves.

## Test conventions

- Tests mirror the `src/` structure under `tests/Go/`
- Functional/integration tests live in `tests/Go/Functional/`
- Test fixtures (stub classes for weaving) live in `tests/Go/Stubs/` and `tests/Fixtures/project/src/` (autoloaded as `Go\Tests\TestProject\`)
- Snapshot fixtures for `WeavingTransformerTest` live in `tests/Go/Instrument/Transformer/_files/`; `*-woven.php` is the transformed source (class→trait), `*-proxy.php` is the generated proxy
- PHPUnit 13+, bootstrap is `vendor/autoload.php` (no separate test bootstrap)
- PHPStan level 10 is a mandatory gate — run `./vendor/bin/phpstan analyze --memory-limit=512M` before every commit
