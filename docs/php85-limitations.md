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

### Final promoted properties and static asymmetric visibility (non-intercepted)

`final` promoted constructor properties and `static` properties with asymmetric visibility
(`public static private(set)`) work on classes that are woven, as long as the properties
themselves are not targeted by `access(...)` pointcuts (see the static-property note below).

### `self`/`parent` reflection resolution

On PHP 8.5, `ReflectionNamedType::getName()` resolves `self`/`parent` return types to concrete
class names. The proxy generators compensate by reading the raw AST type node where available,
so `self`/`parent` keywords are preserved in generated proxies.

## Known limitations

### Closures / first-class callables in attribute arguments — tracked in [#601](https://github.com/goaop/framework/issues/601)

Attribute arguments containing closures or first-class callable syntax (allowed since PHP 8.5)
are not yet correctly copied to generated proxies.

### Promoted-property interception — tracked in [#599](https://github.com/goaop/framework/issues/599)

Constructor-promoted properties (including `final` promoted properties, new in PHP 8.5) cannot be
intercepted via `access(...)` pointcuts.

### Enum constant-expression case values — tracked in [#600](https://github.com/goaop/framework/issues/600)

Backed enum cases declared with constant expressions (e.g. `case Negative = -1;`,
`case Shifted = 1 << 2;`, `case FromConst = self::SHIFT + 10;`) previously lost their values in
the generated proxy enum, producing a fatal error at load time. Fixed by re-emitting the original
case expression verbatim in the proxy enum.

### `new` in initializers under `INTERCEPT_INITIALIZATIONS` — tracked in [#603](https://github.com/goaop/framework/issues/603)

`new` expressions in property/parameter initializers do not work correctly when the
`INTERCEPT_INITIALIZATIONS` kernel feature is enabled.

### Global constants in attribute arguments — tracked in [#602](https://github.com/goaop/framework/issues/602)

Unqualified global constants used in attribute arguments may be mis-resolved when the attribute
is copied into the generated proxy file.

### Class-level attributes on woven classes — tracked in [#598](https://github.com/goaop/framework/issues/598)

Class-level attributes are not correctly carried over to woven classes in all cases.

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
| Final promoted properties (non-intercepted) | ✅ Works | Interception of promoted properties tracked in [#599](https://github.com/goaop/framework/issues/599) |
| Static asymmetric visibility (non-intercepted) | ✅ Preserved | Static properties are never interceptable via `access()` |
| `self`/`parent` reflection resolution | ✅ Handled | Raw AST type nodes used in proxy generation |
| Closures / FCC in attribute arguments | ❌ Limited | Tracked in [#601](https://github.com/goaop/framework/issues/601) |
| Promoted-property interception | ❌ Limited | Tracked in [#599](https://github.com/goaop/framework/issues/599) |
| Enum constant-expression case values | ❌→✅ Fixed | Tracked in [#600](https://github.com/goaop/framework/issues/600) |
| `new` in initializers + `INTERCEPT_INITIALIZATIONS` | ❌ Limited | Tracked in [#603](https://github.com/goaop/framework/issues/603) |
| Global constants in attribute arguments | ❌ Limited | Tracked in [#602](https://github.com/goaop/framework/issues/602) |
| Class-level attributes on woven classes | ❌ Limited | Tracked in [#598](https://github.com/goaop/framework/issues/598) |
| Static property interception via `access()` | ❌ Never | No property hooks for static properties (PHP engine constraint) |
