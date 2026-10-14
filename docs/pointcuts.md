# Pointcut syntax

A pointcut is an expression that selects joinpoints: method executions, property accesses, function calls or object
creation. It is the argument of an advice attribute, e.g. `#[Before('execution(public App\Service->save(*))')]`.

## Pointcut types

| Pointcut                                 | Selects |
|------------------------------------------|---------|
| `execution(modifiers Class->method(*))`  | Execution of a dynamic method |
| `execution(modifiers Class::method(*))`  | Execution of a static method |
| `execution(Namespace\function(*))`       | Calls of a system function from that namespace (`Features::INTERCEPT_FUNCTIONS`) |
| `access(modifiers Class->property)`      | Reads and writes of an instance property |
| `within(ClassFilter)`                    | Every member (methods and properties) of the matching classes |
| `initialization(ClassFilter)`            | Object creation with `new` (`Features::INTERCEPT_INITIALIZATIONS`) |
| `staticinitialization(ClassFilter)`      | The first load of the matching classes |
| `@execution(Attribute)`                  | Methods that carry the attribute |
| `@access(Attribute)`                     | Properties that carry the attribute |
| `@within(Attribute)`                     | Every member of classes that carry the attribute |
| `matchInherited()`                       | Only members inherited from a parent class or imported from a trait; `!matchInherited()` excludes them |
| `matchReturningByReference()`            | Methods and functions declared `function &name()`; `!matchReturningByReference()` excludes them |
| `$this->pointcutName`                    | A named pointcut declared with `#[Pointcut]` on a method of the same aspect |
| `Aspect\Class->pointcutName`             | A named pointcut of another aspect |

The argument list is always `(*)`: arguments are not matched.

## Names and wildcards

| Pattern       | Meaning |
|---------------|---------|
| `*`           | Any part of a name, e.g. `get*`, `*Service`, `App\*\Controller` |
| `**`          | Any namespace depth: `App\**` is every class below `App`, `**->*(*)` is every method |
| `a\|b`        | Alternatives within one name part, e.g. `get*\|is*` |
| `Foo+`        | `Foo` itself, its subclasses and implementers, and classes that use the trait `Foo` (directly or through a parent class) |

Names are matched case-sensitively. Write namespaces with a single backslash; in a PHP string with single quotes
no escaping is needed.

## Modifiers

Method and property pointcuts accept `public`, `protected`, `private`, `final`, `readonly`, and the asymmetric
visibility modifiers `private(set)` and `protected(set)`. Combine alternatives with `|`:
`execution(public|protected App\Service->*(*))`.

Modifiers separated by spaces must all be present, and `|` binds tighter than the space:
`final public|protected` matches final methods that are public or protected. One group of alternatives is supported
per pattern.

Static properties are never intercepted, and neither are readonly properties or properties that already have hooks.
See [PHP 8.4 limitations](php84-limitations.md).

## Return types

A method or function pattern can restrict the declared return type: `execution(public App\Repo->find*(*): App\Entity|null)`.
Union types use `|`, intersection types `&`, and `?Foo` is the same as `Foo|null`.

## Combining pointcuts

| Operator | Meaning |
|----------|---------|
| `&&`     | Both match |
| `\|\|`   | Either matches |
| `!`      | Negation |
| `( )`    | Grouping |

`within(App\**) && !execution(public **->__construct(*))`

Comments start with `//` and run to the end of the line, which helps in long multi-line expressions.

## Named pointcuts

Declare a pointcut once and reference it from several advices:

```php
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\Before;
use Go\Lang\Attribute\Pointcut;

class HealthyLiveAspect implements Aspect
{
    #[Pointcut('execution(public App\Human->eat(*))')]
    protected function humanEat(): void {}

    #[Before('$this->humanEat')]
    public function washUpBeforeEat(MethodInvocation $invocation): void { /* ... */ }

    #[After('$this->humanEat')]
    public function cleanTeethAfterEat(MethodInvocation $invocation): void { /* ... */ }
}
```
