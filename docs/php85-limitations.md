# PHP 8.5 Feature Support & Known Limitations

This document describes how Go! AOP Framework handles PHP 8.5 features in proxy generation and
interception, and what limitations exist. It is based on an audit of the framework on
PHP 8.5.10 and PHP 8.6.0beta2 (see PR #597).

Several of the limitations below are actively being worked on — they are phrased as
"tracked in #NNN" rather than permanent facts, and may already be fixed on master.

## What works

### Pipe operator `|>`

The [pipe operator](https://wiki.php.net/rfc/pipe-operator-v3) works inside woven method bodies.
Method bodies are moved verbatim into the `<Class>OriginalTrait` body trait, so any PHP 8.5 expression syntax
inside them is preserved.

### `clone with`

[`clone with`](https://wiki.php.net/rfc/clone_with_v2) expressions in woven code work as expected.

### `#[\NoDiscard]` attribute

The [`#[\NoDiscard]`](https://wiki.php.net/rfc/marking_return_value_as_important) attribute is
copied to the generated proxy method, and the join-point dispatch returns the intercepted
method's value, so the engine-level "return value not used" warning keeps firing correctly for
woven methods.

### Attributes on class constants

[Attributes on constants](https://wiki.php.net/rfc/attributes-on-constants) — including
`#[\Deprecated]` — are preserved: class constants stay in the woven trait together with their
attributes.

### Closures and first-class callables as parameter defaults

[Closures in constant expressions](https://wiki.php.net/rfc/closures_in_const_expr) used as
parameter default values survive weaving in proxy method signatures.

### Static asymmetric visibility (non-intercepted)

`static` properties with asymmetric visibility (`public static private(set)`) work on classes that are woven; they
are never targeted by `access(...)` pointcuts (see the static-property note below).

### `self`/`parent` reflection resolution

On PHP 8.5, `ReflectionNamedType::getName()` resolves `self`/`parent` return types to concrete
class names. The proxy generators compensate by reading the raw AST type node where available,
so `self`/`parent` keywords are preserved in generated proxies.

## Known limitations

### `new` in initializers is not intercepted

A `new` expression in a constant expression (the default value of a parameter or of a promoted constructor
parameter, a static variable initializer, an attribute argument) is evaluated by the engine. The weaver leaves these
expressions as they are, so the code works, but an `initialization(...)` advice does not run for the objects they
create. Only `new` expressions in executable code are intercepted.

In addition, a class that is created by `new` in the default value of a parameter of an *intercepted* method is
loaded while that method is woven and stays unwoven in that process, so none of its advices apply there (debug mode,
or a production request that weaves on demand). A cache built by `cache:warmup:aop` is not affected. Tracked in
[goaop/parser-reflection#261](https://github.com/goaop/parser-reflection/issues/261).

### Unqualified constants in method attribute arguments, with attribute pointcuts — tracked in [goaop/parser-reflection#259](https://github.com/goaop/parser-reflection/issues/259)

Attributes are copied to the proxy from the source, constants included. But while an attribute pointcut
(`@execution(...)`) is registered, the weaver reads the attributes of every method it matches, and a method attribute
with `true`, `false`, `null` or an unqualified global constant (`PHP_INT_MAX`) as argument makes that fail with a
`WeavingException` ("Namespace  was not found in the file ..."). Class-level attributes are not affected. Until the
fix is released, write such an argument fully qualified (`\true`, `\PHP_INT_MAX`).

### Static properties are never interceptable

Static properties — including PHP 8.5 `static` properties with asymmetric visibility — cannot be
intercepted via `access(...)` pointcuts. PHP property hooks do not exist for static properties,
so the framework has no interception mechanism for them; `AdviceMatcher` excludes static
properties from property join points. This is a PHP engine constraint, not a bug.

## Summary Table

| PHP 8.5 Feature | Interception / Weaving Support | Notes |
|---|:---:|---|
| Pipe operator `\|>` in method bodies | ✅ Works | Bodies are moved verbatim into the woven trait |
| `clone with` | ✅ Works | |
| `#[\NoDiscard]` | ✅ Propagated | Copied to proxy; join-point dispatch returns the value |
| Attributes on class constants (incl. `#[\Deprecated]`) | ✅ Preserved | Constants stay in the woven trait |
| Closures / FCC as parameter defaults | ✅ Works | |
| Static asymmetric visibility (non-intercepted) | ✅ Preserved | Static properties are never interceptable via `access()` |
| `self`/`parent` reflection resolution | ✅ Handled | Raw AST type nodes used in proxy generation |
| `new` in initializers | ❌ Not intercepted | Evaluated by the engine; see also [goaop/parser-reflection#261](https://github.com/goaop/parser-reflection/issues/261) |
| Unqualified constants in method attribute arguments + attribute pointcut | ❌ Limited | Tracked in [goaop/parser-reflection#259](https://github.com/goaop/parser-reflection/issues/259) |
| Static property interception via `access()` | ❌ Never | No property hooks for static properties (PHP engine constraint) |
