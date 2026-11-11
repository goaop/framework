# Recipes

Complete aspects for common concerns. Each one was run against the framework. Adapt namespaces, pointcuts and the
injected services to the project, and register every aspect the way the project does it: through the framework
bridge (`frameworks.md`) or in `configureAop()` (`setup.md`).

Most recipes select methods with a marker attribute. The attribute classes are plain PHP attributes of the
application, shown with each recipe.

A good aspect leaves the contract of the advised method intact: same parameters, same return type, same meaning.
It adds behaviour around the call (logging, limits, retries, transactions) or fails with an exception. Do not use
an advice to make a method return something its signature does not promise.

## Contents

- [Logging with a Loggable attribute](#logging-with-a-loggable-attribute)
- [Caching results](#caching-results)
- [Database transactions](#database-transactions)
- [Retrying failed calls](#retrying-failed-calls)
- [Authorization](#authorization)
- [Metrics for Prometheus](#metrics-for-prometheus)
- [Circuit breaker](#circuit-breaker)
- [Rate limiting and quotas per user](#rate-limiting-and-quotas-per-user)
- [Combining aspects on one method](#combining-aspects-on-one-method)

## Logging with a Loggable attribute

The code says what is logged: `#[Loggable]` on a method, or on a class for all of its public methods. The attribute
carries the settings, so sensitive arguments are switched off where they occur.

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;
use Psr\Log\LogLevel;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final readonly class Loggable
{
    public function __construct(
        public string $level = LogLevel::INFO,
        public bool $skipArguments = false,
        public bool $skipResult = false,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\Loggable;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

final class LoggingAspect implements Aspect
{
    /** @var array<string, Loggable> Settings per method, read from the attributes once */
    private array $settings = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Around('@execution(App\Attribute\Loggable) || (@within(App\Attribute\Loggable) && execution(public **->*(*)) && !execution(public **->__construct(*)))')]
    public function log(MethodInvocation $invocation): mixed
    {
        $method   = $invocation->getMethod();
        $name     = $method->class . '::' . $method->name;
        $loggable = $this->settings[$name] ??= $this->settingsOf($method);
        $context  = $loggable->skipArguments ? [] : ['arguments' => $invocation->getArguments()];
        $start    = hrtime(true);

        try {
            $result = $invocation->proceed();
        } catch (\Throwable $exception) {
            $this->logger->log(LogLevel::ERROR, '{method} failed in {ms} ms: {error}', $context + [
                'method'    => $name,
                'ms'        => round((hrtime(true) - $start) / 1e6, 2),
                'error'     => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $this->logger->log($loggable->level, '{method} returned in {ms} ms', $context + [
            'method' => $name,
            'ms'     => round((hrtime(true) - $start) / 1e6, 2),
        ] + ($loggable->skipResult ? [] : ['result' => $result]));

        return $result;
    }

    private function settingsOf(\ReflectionMethod $method): Loggable
    {
        $attributes = $method->getAttributes(Loggable::class) ?: $method->getDeclaringClass()->getAttributes(Loggable::class);

        return $attributes[0]->newInstance();
    }
}
```

Usage:

```php
#[Loggable]
public function place(Cart $cart): Order { /* ... */ }

#[Loggable(level: LogLevel::DEBUG, skipArguments: true)]   // the arguments hold a password
public function login(string $email, string $password): Session { /* ... */ }
```

To log without touching the classes at all, replace the pointcut with a name pattern such as
`execution(public App\Repository\**->save|delete(*))`. Prefer the attribute: a reader of the method then sees that it
is logged, and the pointcut cannot match methods nobody thought of.

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

## Metrics for Prometheus

Counts the calls and measures the duration of every public service method. The example uses
`promphp/prometheus_client_php`; any metrics client fits the same way.

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Prometheus\CollectorRegistry;

final class MetricsAspect implements Aspect
{
    public function __construct(
        private readonly CollectorRegistry $registry,
    ) {}

    #[Around('execution(public App\Service\**->*(*)) && !execution(public **->__construct(*))')]
    public function measure(MethodInvocation $invocation): mixed
    {
        // getScope() and getMethod()->name create no string per call, unlike (string) $invocation
        $labels = [$invocation->getScope(), $invocation->getMethod()->name];
        $start  = hrtime(true);
        $status = 'ok';

        try {
            return $invocation->proceed();
        } catch (\Throwable $exception) {
            $status = 'error';

            throw $exception;
        } finally {
            $this->registry
                ->getOrRegisterCounter('app', 'method_calls_total', 'Calls of service methods', ['class', 'method', 'status'])
                ->inc([...$labels, $status]);
            $this->registry
                ->getOrRegisterHistogram('app', 'method_duration_seconds', 'Duration of service methods', ['class', 'method'])
                ->observe((hrtime(true) - $start) / 1e9, $labels);
        }
    }
}
```

The registry needs a storage that all PHP workers share (Redis or APCu); an endpoint renders it for the scraper:

```php
// GET /metrics
header('Content-Type: ' . \Prometheus\RenderTextFormat::MIME_TYPE);
echo (new \Prometheus\RenderTextFormat())->render($registry->getMetricFamilySamples());
```

Every call of an advised method writes to the storage, so advise the service boundary, not getters or methods called
in loops. For selected methods only, use a marker attribute and `@execution(App\Attribute\Measured)`.

## Circuit breaker

When a method keeps failing, the breaker opens: for a while every call fails at once with a `CircuitOpenException`
and the broken dependency is not called at all, which keeps its outage from tying up the workers of the application.
After the pause one trial call is let through; its success closes the breaker, its failure opens it again. The state
lives in a cache shared by all workers.

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class CircuitBreaker
{
    /**
     * @param positive-int $failureThreshold Failures in a row that open the breaker
     * @param positive-int $openSeconds      How long calls are rejected before a trial call
     */
    public function __construct(
        public int $failureThreshold = 5,
        public int $openSeconds = 30,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Resilience;

final class CircuitOpenException extends \RuntimeException {}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\CircuitBreaker;
use App\Resilience\CircuitOpenException;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Psr\SimpleCache\CacheInterface;

final class CircuitBreakerAspect implements Aspect
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    #[Around('@execution(App\Attribute\CircuitBreaker)', order: -50)]
    public function guard(MethodInvocation $invocation): mixed
    {
        $method  = $invocation->getMethod();
        $breaker = $method->getAttributes(CircuitBreaker::class)[0]->newInstance();
        $key     = 'circuit.' . hash('xxh128', $method->class . '::' . $method->name);

        /** @var array{failures: int, openedAt: int|null} $state */
        $state = $this->cache->get($key) ?? ['failures' => 0, 'openedAt' => null];

        if ($state['openedAt'] !== null && time() - $state['openedAt'] < $breaker->openSeconds) {
            throw new CircuitOpenException("$method->class::$method->name is switched off after {$state['failures']} failures");
        }

        try {
            $result = $invocation->proceed();
        } catch (\Throwable $exception) {
            $state['failures']++;
            // The threshold is reached, or the trial call of an open breaker failed
            if ($state['failures'] >= $breaker->failureThreshold) {
                $state['openedAt'] = time();
            }
            $this->cache->set($key, $state);

            throw $exception;
        }

        if ($state['failures'] > 0) {
            $this->cache->delete($key);
        }

        return $result;
    }
}
```

Usage: `#[CircuitBreaker(failureThreshold: 3, openSeconds: 60)] public function charge(Payment $payment): Receipt`.

- Callers handle `CircuitOpenException` with a fallback (a cached value, a queued job, an error page).
- The read-modify-write of the state is not atomic: under load a few more calls than the threshold reach the
  dependency, and several trial calls can run at once. That is harmless for a breaker; use Redis counters when the
  numbers must be exact.
- Count only failures of the dependency: catch a narrower exception type when the method also throws for invalid
  input.

## Rate limiting and quotas per user

Limits how often each user may call a method. The counter is a Redis key per user, method and time window, incremented
by a Lua script, so the limit holds across all workers and servers. `CurrentUser` stands for whatever the application
uses to know who is calling.

```php
<?php

declare(strict_types=1);

namespace App\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RateLimit
{
    /**
     * @param positive-int $limit      Calls allowed per window
     * @param positive-int $perSeconds Length of the window: 60 for a rate limit, 86400 for a daily quota
     */
    public function __construct(
        public int $limit,
        public int $perSeconds = 60,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\RateLimit;

final class RateLimitExceededException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Attribute\RateLimit;
use App\RateLimit\RateLimitExceededException;
use App\Security\CurrentUser;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;
use Redis;

final class RateLimitAspect implements Aspect
{
    /** Increments the counter and starts the window with the first call; returns the count and the seconds left */
    private const string SCRIPT = <<<'LUA'
        local count = redis.call('INCR', KEYS[1])
        if count == 1 then
            redis.call('EXPIRE', KEYS[1], ARGV[1])
        end
        return {count, redis.call('TTL', KEYS[1])}
        LUA;

    public function __construct(
        private readonly Redis $redis,
        private readonly CurrentUser $user,
    ) {}

    #[Before('@execution(App\Attribute\RateLimit)', order: -90)]
    public function limit(MethodInvocation $invocation): void
    {
        $method    = $invocation->getMethod();
        $rateLimit = $method->getAttributes(RateLimit::class)[0]->newInstance();
        $key       = "ratelimit:{$this->user->id()}:$method->class::$method->name";

        [$count, $secondsLeft] = $this->redis->eval(self::SCRIPT, [$key, $rateLimit->perSeconds], 1);

        if ($count > $rateLimit->limit) {
            throw new RateLimitExceededException(
                "Limit of $rateLimit->limit calls per $rateLimit->perSeconds s reached for $method->class::$method->name",
                max(1, $secondsLeft),
            );
        }
    }
}
```

Usage: `#[RateLimit(limit: 100, perSeconds: 3600)] public function export(Report $report): string`.

- The HTTP layer turns the exception into a `429 Too Many Requests` response with a `Retry-After` header.
- This is a fixed window: a user can spend two windows' worth of calls around the boundary. Where that matters, use
  a sliding window (a sorted set of timestamps) in the script.
- Rejected calls count as well, so a client that keeps hammering stays blocked until the window ends.
- A `#[Before]` advice runs before every `#[Around]` advice, so the limit is checked before a cache or a
  transaction of the same method.

## Combining aspects on one method

`order` decides the nesting of Around advices of different aspects: lower is outer.

```php
#[Around('@execution(App\Attribute\CircuitBreaker)', order: -50)]  // outermost: an open breaker skips everything
#[Around('@execution(App\Attribute\Cacheable)', order: 10)]        // a hit skips the transaction
#[Around('@execution(App\Attribute\Transactional)', order: 20)]    // innermost
```

Before advices always run before all Around advices, so an authorization or rate-limit `#[Before]` protects a cached
method even when the cache would hit.
