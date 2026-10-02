# Advices

An advice is a public method of an aspect class with one of the advice attributes from `Go\Lang\Attribute`.
It receives the joinpoint, which gives access to the intercepted call. A method carries at most one advice attribute.

## Advice types

| Attribute          | Runs |
|--------------------|------|
| `#[Before]`        | Before the joinpoint. It can change the arguments with `setArguments()`. |
| `#[After]`         | After the joinpoint, also when it throws (like `finally`). |
| `#[AfterThrowing]` | When the joinpoint throws. It receives the exception as the second argument; the exception is rethrown afterwards. |
| `#[Around]`        | Instead of the joinpoint. It decides whether to call `proceed()`, and its return value becomes the result. |

```php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Around;
use Psr\Log\LoggerInterface;

class ServiceAspect implements Aspect
{
    public function __construct(private readonly LoggerInterface $logger) {}

    #[Around('execution(public App\Service\*->*(*))')]
    public function measure(MethodInvocation $invocation): mixed
    {
        $start = hrtime(true);
        try {
            return $invocation->proceed();
        } finally {
            $this->logger->debug($invocation . ' took ' . (hrtime(true) - $start) . ' ns');
        }
    }

    #[AfterThrowing('execution(public App\Service\*->*(*))')]
    public function logFailure(MethodInvocation $invocation, \Throwable $exception): void
    {
        $this->logger->error($invocation . ' failed: ' . $exception->getMessage());
    }
}
```

## Joinpoints

| Joinpoint                                     | Main methods |
|-----------------------------------------------|--------------|
| `MethodInvocation` (dynamic and static)       | `getThis()`, `getScope()`, `getMethod()`, `getArguments()`, `setArguments()`, `proceed()` |
| `FunctionInvocation`                          | `getFunction()`, `getArguments()`, `setArguments()`, `proceed()` |
| `FieldAccess`                                 | `getThis()`, `getField()`, `getAccessType()`, `getValue()`, `getValueToSet()`, `proceed()` |
| `ConstructorInvocation`                       | `getConstructor()`, `getArguments()`, `getThis()` (`null` before the object exists), `proceed()` |

The joinpoints declare `@template` types, so PHPStan and IDEs know the types of `getThis()` and `proceed()`, e.g.
`MethodInvocation<OrderService, Order>`.

## Property interception

`access(...)` pointcuts intercept reads and writes of instance properties through native PHP 8.4 property hooks.
For a write, `proceed()` returns the value that is stored, so an Around advice can change it:

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

## Introductions

`#[DeclareParents]` on a property of the aspect adds an interface and a trait implementing it to the matching
classes:

```php
use Go\Lang\Attribute\DeclareParents;

#[DeclareParents('within(App\Model\*)', interfaceName: \JsonSerializable::class, traitName: JsonSerializableTrait::class)]
protected null $jsonSerializable;
```

## Function interception

With `Features::INTERCEPT_FUNCTIONS`, `execution(App\Service\file_get_contents(*))` intercepts calls of
`file_get_contents()` from code in the `App\Service` namespace that calls the function without a leading backslash.
The advice receives a `FunctionInvocation`.

## Object creation

With `Features::INTERCEPT_INITIALIZATIONS`, `initialization(App\Model\Order)` intercepts `new Order(...)` in woven
files. The advice receives a `ConstructorInvocation`. `staticinitialization(App\Model\*)` runs once, after the class
is loaded.

## Registering aspects

Aspects are services of the aspect container. Register them in `configureAop()` of your kernel, preferably as
deferred services:

```php
protected function configureAop(AspectContainer $container): void
{
    $container->addLazyService(ServiceAspect::class, static fn() => new ServiceAspect(new NullLogger()));
}
```
