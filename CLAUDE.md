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
  - `AbstractMethodInvocation` — base class; holds `protected readonly ?Closure $proceedFn` set once at `injectJoinPoints` time
  - `DynamicClosureMethodInvocation` — `proceed()` calls `($this->proceedFn)($instance, $args)` when set; falls back to `ReflectionMethod::getClosure` for non-trait-engine proxies
  - `StaticClosureMethodInvocation` — same pattern for static methods
  - `ClassFieldAccess` — property interception join point
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

## Test conventions

- Tests mirror the `src/` structure under `tests/Go/`
- Functional/integration tests live in `tests/Go/Functional/`
- Test fixtures (stub classes for weaving) live in `tests/Go/Stubs/` and `tests/Fixtures/project/src/` (autoloaded as `Go\Tests\TestProject\`)
- Snapshot fixtures for `WeavingTransformerTest` live in `tests/Go/Instrument/Transformer/_files/`; `*-woven.php` is the transformed source (class→trait), `*-proxy.php` is the generated proxy
- PHPUnit 11+, bootstrap is `vendor/autoload.php` (no separate test bootstrap)
- PHPStan level 10 is a mandatory gate — run `./vendor/bin/phpstan analyze --memory-limit=512M` before every commit
