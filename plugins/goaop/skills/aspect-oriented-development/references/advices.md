# Advices and joinpoints

Full reference: `docs/advices.md` of the framework (where to find it: `SKILL.md`, "Documentation of the framework").

## Contents

- [Advice types](#advice-types)
- [Execution order](#execution-order)
- [Joinpoint API](#joinpoint-api)
- [Method interception](#method-interception)
- [Property interception](#property-interception)
- [Function interception](#function-interception)
- [Object creation and class loading](#object-creation-and-class-loading)
- [Introductions](#introductions)
- [Type information for PHPStan and IDEs](#type-information-for-phpstan-and-ides)
- [Unit-testing an advice](#unit-testing-an-advice)

## Advice types

All attributes live in `Go\Lang\Attribute` and take `(string $expression, int $order = 0)`.

| Attribute          | Runs | Can do |
|--------------------|------|--------|
| `#[Before]`        | Before the joinpoint | Read and replace arguments (`setArguments()`), throw to prevent the call |
| `#[After]`         | After the joinpoint, also when it threw (like `finally`) | Side effects only; it does not see the result |
| `#[AfterThrowing]` | When the joinpoint threw | Receives the exception as second parameter; the exception is rethrown afterwards |
| `#[Around]`        | Instead of the joinpoint | Everything: skip or repeat `proceed()`, change arguments, change the result, catch exceptions |

Choose the weakest type that does the job: a `#[Before]` advice cannot forget to call `proceed()`.

An advice method is public, carries exactly one advice attribute and takes the joinpoint as first parameter:

```php
#[Before('execution(public App\Service\OrderService->place(*))')]
public function check(MethodInvocation $invocation): void {}

#[AfterThrowing('execution(public App\Service\OrderService->place(*))')]
public function report(MethodInvocation $invocation, \Throwable $exception): void {}

#[Around('execution(public App\Service\OrderService->place(*))')]
public function wrap(MethodInvocation $invocation): mixed
{
    return $invocation->proceed();
}
```

## Execution order

For one joinpoint with several advices:

```
Before (order 1) → Before (order 2)
  → Around (order 1) enters → Around (order 2) enters
    → original method
  ← Around (order 2) leaves ← Around (order 1) leaves
→ AfterThrowing (only when an exception left the chain)
→ After
```

- A lower `order` runs earlier, and for `#[Around]` it is the outer wrapper.
- Before advices run before every Around advice, whatever their order. `order` only sorts advices of the same type.
- Advices with the same order have no guaranteed sequence: set `order` whenever the sequence matters, for example
  authorization (outer) before caching (inner).
- An Around advice that does not call `proceed()` skips the inner Around advices and the method. After advices
  still run.
- AfterThrowing and After cover the Around advices and the method. An exception thrown by a Before advice goes
  straight to the caller: no After and no AfterThrowing advice of that joinpoint runs.

## Joinpoint API

Interfaces live in `Go\Aop\Intercept`. Every joinpoint is `Stringable`: `(string) $invocation` gives
`execution(App\Service\OrderService->place())`, handy for log messages and cache keys. That text is a description,
not a valid pointcut expression.

| Joinpoint | Methods |
|-----------|---------|
| `MethodInvocation` | `proceed()`, `getArguments()`, `setArguments(array)`, `getMethod(): ReflectionMethod`, `getThis(): ?object` (`null` for a static method), `getScope(): string` (the class of the object or of the static call, a subclass for an inherited method; the declaring class is `getMethod()->class`), `isDynamic(): bool` |
| `FunctionInvocation` | `proceed()`, `getArguments()`, `setArguments(array)`, `getFunction(): ReflectionFunction` |
| `FieldAccess` | `proceed()`, `getAccessType(): FieldAccessType` (`Read` or `Write`), `getValue()` (current value), `getValueToSet()` (new value of a write), `getField(): ReflectionProperty`, `getThis(): object`, `getScope()` |
| `ConstructorInvocation` | `proceed()`, `getArguments()`, `setArguments(array)`, `getConstructor(): ?ReflectionMethod`, `getThis(): ?object` (`null` before the object exists), `getScope()` |

`getArguments()` returns the arguments by position, as passed by the caller. Trailing parameters the caller omitted
are missing from the list (their defaults are not filled in), so check `isset($arguments[1])` before reading an
optional one. Named arguments arrive in their positional slots, and variadic arguments are appended one by one. Map
names with `$invocation->getMethod()->getParameters()` when the advice needs a parameter by name.

## Method interception

Changing the arguments:

```php
#[Before('execution(public App\Service\Mailer->send(*))')]
public function normalizeRecipient(MethodInvocation $invocation): void
{
    $arguments    = $invocation->getArguments();
    $arguments[0] = strtolower(trim($arguments[0]));
    $invocation->setArguments($arguments);
}
```

Replacing the result or skipping the call:

```php
#[Around('execution(public App\Service\FeatureFlags->isEnabled(*))')]
public function forceInTests(MethodInvocation $invocation): mixed
{
    if ($this->overrides->has($invocation->getArguments()[0])) {
        return true;               // the original method does not run
    }

    return $invocation->proceed();
}
```

Reading the settings of a marker attribute:

```php
#[Around('@execution(App\Attribute\Cacheable)')]
public function cache(MethodInvocation $invocation): mixed
{
    $cacheable = $invocation->getMethod()->getAttributes(Cacheable::class)[0]->newInstance();
    // $cacheable->ttl ...
}
```

Things to know:

- `getThis()` is the real object, so an advice can call its other public methods. Those calls are intercepted too
  when a pointcut matches them: avoid advising a method the advice itself calls on the same object.
- A method inherited from a woven parent runs the advice once, not once per class of the hierarchy.
- A generator method: the advice runs at the call and `proceed()` returns the `Generator`. The yielded values are
  produced later, outside the advice.
- A method returning by reference (`function &name()`) cannot be intercepted and fails to weave: exclude it with
  `&& !matchReturningByReference()`.
- A `void` method: return `$invocation->proceed()` from an Around advice all the same, the result is `null`.
- Constructors match `execution(public Foo->__construct(*))` like any method. Exclude them from broad pointcuts
  unless you mean them.
- Magic calls: advise `__call` or `__callStatic` and read the invoked name from the arguments
  (`[$name, $arguments] = $invocation->getArguments();`).

## Property interception

`access(...)` intercepts reads and writes of instance properties through native property hooks. The advice sees both,
so branch on the access type. For a write, `proceed()` returns the value that is stored; return something else to
store that instead.

```php
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;

#[Around('access(public App\Entity\User->email)')]
public function normalizeEmail(FieldAccess $access): mixed
{
    if ($access->getAccessType() === FieldAccessType::Write) {
        return strtolower($access->proceed());
    }

    return $access->proceed();
}
```

Constructor-promoted properties are intercepted like declared ones, the assignment by the constructor included.

Not intercepted, without an error: static properties, `readonly` properties, properties that already have hooks,
inherited private and inherited final properties. Accesses made by reflection or by
an ORM that writes raw values (Doctrine hydration) bypass the advice as well.

## Function interception

`execution(App\Service\file_get_contents(*))` intercepts calls of `file_get_contents()` made by code in the namespace
`App\Service`. It works because PHP resolves an unqualified function call in the current namespace first, and the
framework declares a function there.

- The call must be unqualified: `\file_get_contents()` and a function imported with `use function` are not
  intercepted.
- The namespace in the pattern is that of the calling code, not of the function. Wildcards and alternatives work
  as for classes: `App\**\time|random_int(*)`.
- Code in the global namespace cannot be intercepted this way.
- Every intercepted call pays for the interceptor. Advise the few functions that matter (I/O, clock, randomness)
  instead of `App\**\*(*)`.

```php
#[Around('execution(App\Service\time(*))')]
public function freezeTime(FunctionInvocation $invocation): int
{
    return $this->clock?->now()->getTimestamp() ?? $invocation->proceed();
}
```

## Object creation and class loading

`initialization(App\Model\Order)` intercepts `new Order(...)` written in woven files; the advice gets a
`ConstructorInvocation`. While such a pointcut is registered, every `new` expression in woven files goes through the
interceptor, which costs time: keep it for the cases that need it, and prefer advising the constructor
(`execution(public App\Model\Order->__construct(*))`) when the advice does not have to replace the object.

`staticinitialization(App\Model\*)` runs an advice once, right after the class was loaded.

## Introductions

`#[DeclareParents]` sits on a property of the aspect and adds an interface plus a trait implementing it to the
matching classes. The property is a placeholder: its name and value are not used.

```php
use Go\Lang\Attribute\DeclareParents;

final class SerializationAspect implements Aspect
{
    #[DeclareParents('within(App\Dto\*)', interfaceName: \JsonSerializable::class, traitName: JsonSerializableTrait::class)]
    protected null $jsonSerializable;
}
```

The introduced interface is real: `$dto instanceof \JsonSerializable` is true. Static analysis does not know it,
because the source file is unchanged; narrow with `instanceof` where the code needs the methods.

## Type information for PHPStan and IDEs

The joinpoint interfaces are generic. Annotate the parameter when the pointcut targets one type:

```php
/**
 * @param MethodInvocation<OrderService, Order> $invocation instance type, return type
 */
#[Around('execution(public App\Service\OrderService->place(*))')]
public function wrap(MethodInvocation $invocation): Order
{
    $invocation->getThis();   // OrderService|null
    return $invocation->proceed();   // Order
}
```

`FieldAccess<T, V>` takes the class and the property type, `FunctionInvocation<V>` the return type,
`ConstructorInvocation<T>` the class. For instance methods only, `DynamicMethodInvocation<T, V>` makes `getThis()`
non-nullable.

## Unit-testing an advice

An aspect is an ordinary class and the joinpoints are interfaces, so an advice is tested without weaving:

```php
public function testReturnsCachedValueWithoutProceeding(): void
{
    $invocation = $this->createMock(MethodInvocation::class);
    $invocation->method('getArguments')->willReturn([42]);
    $invocation->method('getMethod')->willReturn(new \ReflectionMethod(ProductRepository::class, 'find'));
    $invocation->expects($this->never())->method('proceed');

    $aspect = new CachingAspect($this->cacheWithEntryFor(42));

    self::assertSame('cached', $aspect->cache($invocation));
}
```

Whether the pointcut matches the right methods is a separate question, answered by `debug:advisor` or by an
integration test that runs with an initialized kernel (`setup.md`, "Tests", or `frameworks.md`).
