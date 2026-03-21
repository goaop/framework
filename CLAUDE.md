# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Go! AOP Framework** — an Aspect-Oriented Programming (AOP) framework for PHP 8.2+. It intercepts PHP class/method/function execution transparently by transforming source code at load time via a custom PHP stream wrapper, without requiring PECL extensions, annotations at runtime, or eval.

Package: `goaop/framework` | Namespace root: `Go\` | PHP: `^8.2`

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

# Static analysis (level 4, src/ only)
./vendor/bin/phpstan analyze

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
- `SelfValueTransformer` — rewrites `self::` to use the concrete proxy class
- `WeavingTransformer` — main transformer; uses `AdviceMatcher` to find applicable advices and `CachedAspectLoader` for aspect metadata, then delegates to proxy generators
- `MagicConstantTransformer` — rewrites `__FILE__`/`__DIR__` so they resolve to the original file, not the cached proxy

Each transformer returns `TransformerResultEnum`: `RESULT_TRANSFORMED`, `RESULT_ABSTAIN`, or `RESULT_ABORTED`.

### Proxy generation (`src/Proxy/`)

- `ClassProxyGenerator` — generates a proxy subclass with overridden interceptable methods
- `FunctionProxyGenerator` — generates function wrappers
- `TraitProxyGenerator` — generates trait proxies
- `src/Proxy/Part/` — individual code-generation components (method lists, parameter lists, joinpoint property injection)

### AOP core (`src/Aop/`)

- `src/Aop/Intercept/` — interfaces: `Joinpoint`, `Invocation`, `MethodInvocation`, `ConstructorInvocation`, `FunctionInvocation`, `FieldAccess`
- `src/Aop/Framework/` — concrete invocation implementations used at runtime by proxies; `AbstractMethodInvocation`, `DynamicClosureMethodInvocation`, `StaticClosureMethodInvocation`, `ClassFieldAccess`, etc.
- `src/Aop/Pointcut/` — LALR pointcut grammar (`PointcutGrammar`, `PointcutParser`, `PointcutLexer`, `PointcutParseTable`) and pointcut combinators (`AndPointcut`, `OrPointcut`, `NotPointcut`, `NamePointcut`, `AttributePointcut`, etc.)
- `src/Lang/Attribute/` — PHP 8 attributes for declaring aspects and advice: `#[Aspect]`, `#[Before]`, `#[After]`, `#[Around]`, `#[AfterThrowing]`, `#[Pointcut]`, `#[DeclareError]`, `#[DeclareParents]`
- `src/Aop/Features.php` — bitmask enum for optional features (`INTERCEPT_FUNCTIONS`, `INTERCEPT_INITIALIZATIONS`, `INTERCEPT_INCLUDES`)

### Container and aspect loading (`src/Core/`)

- `Container.php` — DI container with `add()` (by class-string or key), `getService()`, `addLazyService()` (Closure), and automatic tagging by interface
- `AspectLoader` / `CachedAspectLoader` — scan aspect classes for pointcut/advice attributes and produce `Advisor` instances
- `AttributeAspectLoaderExtension` — handles PHP 8 attribute-based aspect definitions
- `AdviceMatcher` — given a class reflector, returns the set of applicable advisors keyed by join point

### Bridge

`src/Bridge/Doctrine/MetadataLoadInterceptor.php` — workaround for Doctrine ORM entity weaving (Doctrine loads metadata before the kernel can intercept classes).

## Test conventions

- Tests mirror the `src/` structure under `tests/Go/`
- Functional/integration tests live in `tests/Go/Functional/`
- Test fixtures (stub classes for weaving) live in `tests/Go/Stubs/` and `tests/Fixtures/project/src/` (autoloaded as `Go\Tests\TestProject\`)
- PHPUnit 10, bootstrap is `vendor/autoload.php` (no separate test bootstrap)
- PHPStan baseline is `phpstan-baseline.php` — add new accepted errors there rather than inline suppression when appropriate