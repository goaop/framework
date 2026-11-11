# Pointcut expressions

A pointcut selects joinpoints by their declaration. It is the first argument of an advice attribute:
`#[Before('execution(public App\Service\OrderService->place(*))')]`.

Source of truth for the installed version: `vendor/goaop/framework/docs/pointcuts.md`.

## Contents

- [Pointcut types](#pointcut-types)
- [Names and wildcards](#names-and-wildcards)
- [Modifiers](#modifiers)
- [Return types](#return-types)
- [Combining pointcuts](#combining-pointcuts)
- [Named pointcuts](#named-pointcuts)
- [From intent to expression](#from-intent-to-expression)
- [Common mistakes](#common-mistakes)

## Pointcut types

| Pointcut                                | Selects | Joinpoint passed to the advice |
|-----------------------------------------|---------|--------------------------------|
| `execution(modifiers Class->method(*))` | Execution of an instance method | `MethodInvocation` |
| `execution(modifiers Class::method(*))` | Execution of a static method | `MethodInvocation` |
| `execution(Namespace\function(*))`      | Calls of a function from code in that namespace | `FunctionInvocation` |
| `access(modifiers Class->property)`     | Reads and writes of an instance property | `FieldAccess` |
| `initialization(Class)`                 | Object creation with `new` in woven files | `ConstructorInvocation` |
| `staticinitialization(Class)`           | The first load of the class | `ClassJoinpoint` |
| `within(Class)`                         | Every method and property of the matching classes | by member |
| `@execution(Attribute)`                 | Methods that carry the attribute (or a subclass of it) | `MethodInvocation` |
| `@access(Attribute)`                    | Properties that carry the attribute | `FieldAccess` |
| `@within(Attribute)`                    | Every member of classes that carry the attribute | by member |
| `matchInherited()`                      | Members inherited from a parent class or imported from a trait | — |
| `matchReturningByReference()`           | Methods and functions declared `function &name()` | — |
| `$this->name`                           | A named pointcut of the same aspect | — |
| `App\Aspect\Other->name`                | A named pointcut of another aspect | — |

`->` and `::` are different joinpoints: `Class->method` never matches a static method.

The argument list is always the literal `(*)`. Arguments are not matched.

`matchInherited()` and `matchReturningByReference()` are filters: use them with `&&`, usually negated.

## Names and wildcards

| Pattern | Meaning |
|---------|---------|
| `*`     | Any characters inside one name part, including none. It never crosses a `\`. `get*` also matches `get`; `App\*\Controller` has exactly one level between. |
| `**`    | Any namespace depth. `App\**` is every class below `App`; `**->*(*)` is every method of every woven class. |
| `a\|b`  | Alternatives inside one name part: `get*\|is*`. `App\Cart\|Order` means `App\Cart` or `App\Order`, never a global `Order`. |
| `Foo+`  | `Foo`, its subclasses, its implementers when `Foo` is an interface, and classes using it when `Foo` is a trait. |

Names are case-sensitive. Write class names fully qualified, without a leading backslash; `use` imports of the aspect
file do not apply inside the string.

## Modifiers

`public`, `protected`, `private`, `final`, `readonly`, and for properties `private(set)` and `protected(set)`.

- `|` joins alternatives: `public|protected`.
- A space means "and", and `|` binds tighter: `final public|protected` is final and (public or protected).
- A method or property pattern without a modifier is a syntax error in `execution(Class->method(*))` and
  `access(...)`: always state the visibility. Function patterns take none.

## Return types

Restrict a method or function pattern to a declared return type:

```
execution(public App\Repository\*->find*(*): App\Entity\User|null)
```

`|` is a union, `&` an intersection, `?Foo` equals `Foo|null`.

## Combining pointcuts

`&&`, `||`, `!` and parentheses. `//` starts a comment up to the end of the line, useful in a multi-line nowdoc.

```php
#[Around(<<<'POINTCUT'
    within(App\Service\**)
    && execution(public **->*(*))
    && !execution(public **->__construct(*))   // constructors are not business calls
    && !matchInherited()
    POINTCUT)]
```

## Named pointcuts

Declare an expression once, on an empty method, and reference it from several advices:

```php
#[Pointcut('execution(public App\Service\**->*(*)) && !execution(public **->__construct(*))')]
protected function serviceCall(): void {}

#[Before('$this->serviceCall')]
public function before(MethodInvocation $invocation): void { /* ... */ }

#[AfterThrowing('$this->serviceCall && !within(App\Service\Internal\**)')]
public function onError(MethodInvocation $invocation, \Throwable $exception): void { /* ... */ }
```

## From intent to expression

| Intent | Expression |
|--------|------------|
| One method | `execution(public App\Service\OrderService->place(*))` |
| Every public method of a class | `execution(public App\Service\OrderService->*(*))` |
| Public methods of the classes directly in a namespace | `execution(public App\Service\*->*(*))` |
| Public methods of every class below a namespace | `execution(public App\Service\**->*(*))` |
| Getters and issers | `execution(public App\Model\**->get*\|is*(*))` |
| Two methods by name, in every class of a namespace | `execution(public App\Repository\**->save\|delete(*))` |
| Every implementation of an interface | `execution(public App\Contract\Handler+->handle(*))` |
| Every repository class by name | `execution(public **\*Repository->*(*))` |
| Methods marked with an attribute | `@execution(App\Attribute\Transactional)` |
| Every public method of classes marked with an attribute | `@within(App\Attribute\Audited) && execution(public **->*(*))` |
| Static factory methods | `execution(public App\Model\**::create*(*))` |
| A private method | `execution(private App\Service\OrderService->validate(*))` |
| Public and protected methods | `execution(public\|protected App\Service\OrderService->*(*))` |
| Magic calls | `execution(public App\Model\Record->__call(*))` (the advice reads the name from the arguments) |
| Only methods the class declares itself | `execution(public App\Service\**->*(*)) && !matchInherited()` |
| A property | `access(public App\Entity\User->email)` |
| Every public property of a class | `access(public App\Entity\User->*)` |
| Calls of a PHP function from one namespace | `execution(App\Service\file_get_contents(*))` |
| Creation of an object | `initialization(App\Model\Order)` |
| Classes of two namespaces | `execution(public App\A\Foo->*(*)) \|\| execution(public App\B\Bar->*(*))` |

In a PHP string write `|` and `||` plainly; the backslash before `|` above only escapes the Markdown table.

## Common mistakes

| Written | Problem | Correct |
|---------|---------|---------|
| `execution(public App\Service->save())` | Empty argument list is a syntax error | `->save(*)` |
| `execution(App\Service->save(*))` | No visibility | `execution(public App\Service->save(*))` |
| `execution(public App\*->*(*))` for nested namespaces | `*` stops at `\` | `App\**->*(*)` |
| `execution(public App\Service->create(*))` for a static method | `->` is instance only | `App\Service::create(*)` |
| `"execution(public App\\Service->$method(*))"` in double quotes | Escapes and interpolation | Single quotes |
| `execution(public Service->save(*))` with `use App\Service;` | Imports do not apply | Fully qualified name |
| `execution(public App\Foo\A\|App\Bar\B->*(*))` | `\|` stays inside one name part | Two pointcuts joined with `\|\|` |
| `execution(public App\Contract\Handler->handle(*))` for implementers | Matches the interface name only | `Handler+` |
| `within(App\Service\**)` to log method calls | Also matches properties and constructors | Add `&& execution(public **->*(*))` |
| `execution(public **->*(*))` | Weaves every class below the include paths, constructors included | Narrow by namespace, type or attribute |

Check a pointcut with `vendor/bin/aspect debug:advisor <loader> --advisor='<Aspect>-><method>'`: it prints the
members that match. An invalid expression fails with an "Unexpected token" parser exception or
`PointcutSyntaxException`, naming the position.
