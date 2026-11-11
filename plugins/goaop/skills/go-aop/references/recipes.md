# Recipes

Complete aspects for common concerns. Each one was run against the framework. Adapt namespaces, pointcuts and the
injected services to the project; register every aspect in `configureAop()` (see `setup.md`).

Most recipes select methods with a marker attribute. The attribute classes are plain PHP attributes of the
application, shown with each recipe.

## Contents

- [Logging method calls and failures](#logging-method-calls-and-failures)
- [Caching results](#caching-results)
- [Database transactions](#database-transactions)
- [Retrying failed calls](#retrying-failed-calls)
- [Authorization](#authorization)
- [Deprecation notices](#deprecation-notices)
- [Fluent setters](#fluent-setters)
- [Magic methods](#magic-methods)
- [Combining aspects on one method](#combining-aspects-on-one-method)

## Logging method calls and failures

A named pointcut shared by two advices. Constructors are excluded: `**->*(*)` matches them too.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\AfterThrowing;
use Go\Lang\Attribute\Before;
use Go\Lang\Attribute\Pointcut;
use Psr\Log\LoggerInterface;

final class LoggingAspect implements Aspect
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Pointcut('execution(public App\Service\**->*(*)) && !execution(public **->__construct(*))')]
    protected function serviceCall(): void {}

    #[Before('$this->serviceCall')]
    public function logCall(MethodInvocation $invocation): void
    {
        $this->logger->info('Calling ' . $invocation, ['arguments' => $invocation->getArguments()]);
    }

    #[AfterThrowing('$this->serviceCall')]
    public function logFailure(MethodInvocation $invocation, \Throwable $exception): void
    {
        $this->logger->error($invocation . ' failed: ' . $exception->getMessage(), ['exception' => $exception]);
    }
}
```

Arguments can hold secrets and large objects: log them only where that is acceptable, or log their types.

## Caching results

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Cacheable
{
    public function __construct(
        public int $ttl = 3600,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\Cacheable;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Psr\SimpleCache\CacheInterface;

final class CachingAspect implements Aspect
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    #[Around('@execution(App\Attribute\Cacheable)')]
    public function cache(MethodInvocation $invocation): mixed
    {
        $method = $invocation->getMethod();
        // PSR-16 keys must not contain {}()/\@: so hash class, method and arguments
        $key = 'aop.' . hash('xxh128', $invocation->getScope() . '::' . $method->name . serialize($invocation->getArguments()));

        // A wrapper array tells a cached null from a miss
        $hit = $this->cache->get($key);
        if (is_array($hit)) {
            return $hit[0];
        }

        $result = $invocation->proceed();
        $ttl    = $method->getAttributes(Cacheable::class)[0]->newInstance()->ttl;
        $this->cache->set($key, [$result], $ttl);

        return $result;
    }
}
```

Usage: `#[Cacheable(ttl: 300)] public function find(int $id): ?Product`. The key ignores the state of the object, so
cache only methods whose result depends on the arguments alone, with serializable arguments and results.

### In memory, for one request

Without a cache service: results are kept per object in a `WeakMap`, so they disappear with the object, and a second
advice drops them when the object is written to.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\After;
use Go\Lang\Attribute\Around;
use WeakMap;

final class MemoizeAspect implements Aspect
{
    /** @var WeakMap<object, array<string, mixed>> */
    private WeakMap $results;

    public function __construct()
    {
        $this->results = new WeakMap();
    }

    #[Around('execution(public App\Repository\**->find*(*))')]
    public function memoize(MethodInvocation $invocation): mixed
    {
        $object = $invocation->getThis();
        if ($object === null) {
            return $invocation->proceed();
        }

        $key     = $invocation->getMethod()->name . serialize($invocation->getArguments());
        $results = $this->results[$object] ?? [];
        // array_key_exists(): a null result is cached too
        if (!array_key_exists($key, $results)) {
            $results[$key]           = $invocation->proceed();
            $this->results[$object] = $results;
        }

        return $results[$key];
    }

    #[After('execution(public App\Repository\**->save|delete(*))')]
    public function forget(MethodInvocation $invocation): void
    {
        $object = $invocation->getThis();
        if ($object !== null) {
            unset($this->results[$object]);
        }
    }
}
```

## Database transactions

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Transactional {}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use PDO;

final class TransactionAspect implements Aspect
{
    public function __construct(
        private readonly PDO $connection,
    ) {}

    #[Around('@execution(App\Attribute\Transactional)')]
    public function transactional(MethodInvocation $invocation): mixed
    {
        // A transactional method called from another one joins the open transaction
        if ($this->connection->inTransaction()) {
            return $invocation->proceed();
        }

        $this->connection->beginTransaction();
        try {
            $result = $invocation->proceed();
            $this->connection->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }
}
```

With Doctrine ORM the body is `return $this->entityManager->wrapInTransaction(static fn() => $invocation->proceed());`.

## Retrying failed calls

Calling `proceed()` again runs the method again, with the same arguments.

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Retry
{
    /**
     * @param positive-int                 $attempts Total number of calls
     * @param class-string<\Throwable>     $on       Exception type that triggers a retry
     */
    public function __construct(
        public int $attempts = 3,
        public int $delayMs = 100,
        public string $on = \RuntimeException::class,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\Retry;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

final class RetryAspect implements Aspect
{
    #[Around('@execution(App\Attribute\Retry)')]
    public function retry(MethodInvocation $invocation): mixed
    {
        $retry = $invocation->getMethod()->getAttributes(Retry::class)[0]->newInstance();

        for ($attempt = 1; ; $attempt++) {
            try {
                return $invocation->proceed();
            } catch (\Throwable $exception) {
                if (!$exception instanceof $retry->on || $attempt >= $retry->attempts) {
                    throw $exception;
                }
                usleep($retry->delayMs * 1000 * $attempt);
            }
        }
    }
}
```

Retry only idempotent operations. `attempts` counts all calls: "retry up to 4 times" is `attempts: 5`.

## Authorization

A `#[Before]` advice that throws prevents the call. `CurrentUser` stands for whatever the application uses to know
who is logged in.

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class RequiresRole
{
    public function __construct(
        public string $role,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\RequiresRole;
use App\Security\AccessDeniedException;
use App\Security\CurrentUser;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

final class AuthorizationAspect implements Aspect
{
    public function __construct(
        private readonly CurrentUser $user,
    ) {}

    // On a method, or on a class for all of its public methods
    #[Before('@execution(App\Attribute\RequiresRole) || (@within(App\Attribute\RequiresRole) && execution(public **->*(*)))', order: -100)]
    public function checkRole(MethodInvocation $invocation): void
    {
        $method     = $invocation->getMethod();
        $attributes = $method->getAttributes(RequiresRole::class) ?: $method->getDeclaringClass()->getAttributes(RequiresRole::class);

        foreach ($attributes as $attribute) {
            $role = $attribute->newInstance()->role;
            if (!$this->user->hasRole($role)) {
                throw new AccessDeniedException("Role $role is required for $invocation");
            }
        }
    }
}
```

The check guards the method, wherever the call comes from. It is no replacement for the access control of the HTTP
layer, which also protects code that is not woven.

## Deprecation notices

Report every call of the methods you want to remove, with the caller, without editing them:

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

final class DeprecationAspect implements Aspect
{
    #[Before('execution(public App\Legacy\**->*(*))')]
    public function report(MethodInvocation $invocation): void
    {
        trigger_error($invocation . ' is deprecated, use App\Service instead', E_USER_DEPRECATED);
    }
}
```

## Fluent setters

`Foo+` selects the classes that opt in through a marker interface; the Around advice replaces the `null` result of a
setter with the object.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

final class FluentInterfaceAspect implements Aspect
{
    #[Around('within(App\Model\Fluent+) && execution(public **->set*(*))')]
    public function returnThis(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed() ?? $invocation->getThis();
    }
}
```

The setters must not declare a `void` return type, or PHP discards the returned object.

## Magic methods

There is no pointcut for methods that do not exist. Advise `__call` (or `__callStatic` with `::`) and filter by the
invoked name inside the advice:

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

final class MagicFinderAspect implements Aspect
{
    #[Around('execution(public App\Model\Record->__call(*))')]
    public function aroundFinders(MethodInvocation $invocation): mixed
    {
        [$name, $arguments] = $invocation->getArguments();
        if (!str_starts_with($name, 'findBy')) {
            return $invocation->proceed();
        }

        // ... handle findBy*() calls here, $arguments holds their arguments
        return $invocation->proceed();
    }
}
```

## Combining aspects on one method

`order` decides the nesting of Around advices of different aspects: lower is outer.

```php
#[Around('@execution(App\Attribute\Cacheable)', order: 10)]      // outer: a hit skips the transaction
#[Around('@execution(App\Attribute\Transactional)', order: 20)]  // inner
```

Before advices always run before all Around advices, so an authorization `#[Before]` protects a cached method even
when the cache would hit.
