# Catalogue of cross-cutting concerns

How each concern looks when it is scattered through object-oriented code, which advice it becomes, and what goes
wrong when extracting it. Complete aspects for most of them are in `references/recipes.md` of the
`aspect-oriented-development` skill: start from those instead of writing an aspect from nothing. A recipe shows a good
default; the existing code decides. Compare the recipe with the original line by line (keys, messages, delays, which
results are cached, which exceptions count) and change the recipe wherever they differ.

## Contents

- [Concerns](#concerns)
- [Related refactorings](#related-refactorings)
- [Lessons from AspectJ and Spring](#lessons-from-aspectj-and-spring)

## Concerns

### Logging and tracing

```php
$this->logger->info('Calling ' . __METHOD__, ['arguments' => func_get_args()]);
```

- **Search for**: `->info(`, `->debug(`, `->error(`, `Log::`, `error_log(`, `__METHOD__`.
- **Advice**: `#[Before]` for entry records, `#[AfterThrowing]` for failures, `#[Around]` when one record holds the
  result or the duration.
- **Attribute**: `#[Loggable]` with the level and what to leave out.
- **Pitfalls**: Only *uniform* log lines are a concern. A message that explains a business decision ("order held:
  credit limit exceeded") belongs to the method and stays. Arguments may hold secrets the original code logged
  selectively: keep that per method. Message texts and context keys are often parsed by dashboards and alerts:
  preserve them exactly.

### Transactions

```php
$this->db->beginTransaction();
try {
    // work
    $this->db->commit();
} catch (\Throwable $e) {
    $this->db->rollBack();
    throw $e;
}
```

- **Search for**: `beginTransaction`, `commit(`, `rollBack`, `wrapInTransaction`, `transactional(`, `DB::transaction`.
- **Advice**: `#[Around]`.
- **Attribute**: `#[Transactional]`.
- **Pitfalls**: Which exceptions roll back? Keep the original's rule (`\Throwable`, or only some types). What happens
  when a transactional method calls another one: the original code may have nested transactions, used savepoints, or
  relied on only the outer method opening one. Calls through `$this` are intercepted, so an inner transactional
  method does reach the advice: decide between joining the open transaction and nesting, and test it. A method that
  commits early and continues working cannot be expressed by one advice.

### Authorization and guards

```php
if (!$this->security->isGranted('ROLE_ADMIN')) {
    throw new AccessDenied('ROLE_ADMIN is required');
}
```

- **Search for**: `isGranted`, `denyAccessUnlessGranted`, `authorize(`, `->can(`, `hasRole`, `Gate::`, the
  exception class.
- **Advice**: `#[Before]`.
- **Attribute**: `#[RequiresRole('...')]`, `#[RequiresPermission('...')]`.
- **Pitfalls**: A check that depends on an argument (`$this->security->canEdit($order)`) is still a guard: read the
  argument from the joinpoint, by position. A missing attribute now means "unguarded" and nothing fails: after the
  migration, compare the list of guarded methods with the original one. A check in the middle of a method, after
  data was loaded, is business logic.

### Caching and memoization

```php
$key = 'product.' . md5(__METHOD__ . serialize(func_get_args()));
$cached = $this->cache->get($key);
if ($cached !== null) {
    return $cached;
}
// work
$this->cache->set($key, $result, 300);
```

- **Search for**: `cache`, `->getItem(`, `->remember(`, `static $cache`, `isset($this->loaded[`.
- **Advice**: `#[Around]`.
- **Attribute**: `#[Cacheable(ttl: 300)]`, and `#[CacheEvict]` for the methods that invalidate.
- **Pitfalls**: Existing cache keys are data: other code, other services or a warm production cache may depend on
  their exact format, so build the same keys. Copies differ in how they treat `null`, `false` and empty arrays:
  keep each behaviour or ask. Invalidation is part of the concern; find the `delete()` calls and extract them too.
  Keys that include object state need that state to be reachable (`extract-advice.md`).

### Timing, metrics and profiling

```php
$start = microtime(true);
// work
$this->metrics->timing('orders.place', microtime(true) - $start);
```

- **Search for**: `microtime(`, `hrtime(`, `stopwatch`, `->increment(`, `->timing(`, `->observe(`.
- **Advice**: `#[Around]`; `#[Before]` or `#[After]` for a plain counter.
- **Attribute**: `#[Timed('orders.place')]`, `#[Counted]`.
- **Pitfalls**: Metric names are an interface to dashboards: keep them, as attribute arguments if they do not follow
  from the method name. Check whether the original measured failures too (was the second line in a `finally`?).

### Retry

```php
$attempt = 0;
while (true) {
    try {
        return $this->client->call($request);
    } catch (TransientException $e) {
        if (++$attempt >= 3) {
            throw $e;
        }
        usleep(100_000);
    }
}
```

- **Search for**: loops containing `try`, `attempt`, `retries`, `usleep(`, `sleep(`.
- **Advice**: `#[Around]`, calling `proceed()` again.
- **Attribute**: `#[Retry(attempts: 3, delayMs: 100, on: TransientException::class)]`.
- **Pitfalls**: Count the same way as the original (attempts or retries), and wait the same way: a constant delay,
  a growing one, or none. Put the attribute on the method that makes
  the one call, not on a method that does other work before it, or that work is repeated too. A retry inside a
  transaction repeats queries in the same transaction: the retry aspect usually has to be outside (lower `order`).

### Error handling and exception translation

```php
try {
    // work
} catch (\PDOException $e) {
    throw new StorageException('Could not save the order', previous: $e);
}
```

- **Search for**: `catch` blocks with identical bodies (`grep -A4 'catch ('`).
- **Advice**: `#[AfterThrowing]` when the handler reports and rethrows the same exception; `#[Around]` when it
  converts, wraps or swallows.
- **Attribute**: `#[TranslatesExceptions]`, `#[ReportsFailures]`, or by layer when the rule is "every repository".
- **Pitfalls**: Handlers that return a fallback value change the result: `#[Around]`, and the fallback is a setting.
  An empty `catch` that swallows errors is a bug to raise with the user, not a behaviour to preserve silently.

### Validation of arguments and results

- **Shape**: identical `if (...) { throw new InvalidArgumentException(...); }` at the start of many methods.
- **Advice**: `#[Before]`; `#[Around]` for checks of the result.
- **Pitfalls**: Usually this is *not* a cross-cutting concern: the rule belongs to a value object or to the type
  system. Extract only checks that are the same for a whole family of methods and independent of their meaning
  ("the tenant of the argument is the current tenant").

### Events and audit trails

```php
$this->events->dispatch(new OrderCancelled($orderId));
```

- **Advice**: `#[Around]` or `#[After]`, on success only if the original dispatched after the work succeeded.
- **Attribute**: `#[Audited('order.cancel')]`.
- **Pitfalls**: Domain events that carry business data built by the method are business logic. Uniform audit records
  ("who called what with which arguments") are a concern.

### Locking and resource management

```php
$lock = $this->locks->acquire('report');
try {
    // work
} finally {
    $lock->release();
}
```

- **Advice**: `#[Around]`.
- **Attribute**: `#[Locked('report')]`.
- **Pitfalls**: A lock name computed from arguments needs a rule the advice can apply to every marked method (an
  argument position in the attribute, or an interface on the argument).

### Rate limits, quotas, circuit breakers, feature flags, deprecation

Rarely found as scattered code, more often missing altogether. When the user asks for them during a refactoring,
that is new behaviour: use the recipes of the `aspect-oriented-development` skill and keep it separate from the
behaviour-preserving steps.

## Related refactorings

- **Replace a decorator with an aspect.** A `LoggingOrderService` that wraps `OrderService` and forwards every
  method is a hand-written aspect for one class. When the same kind of decorator exists for many classes, extract
  the forwarding logic into an advice, mark the methods of the wrapped class, delete the decorators and their
  container wiring. Keep a decorator that exists for one class only: it is explicit and cheap.
- **Replace a template method with an aspect.** A base class whose `execute()` logs, opens a transaction and calls
  an abstract `doExecute()` forces every subclass into one hierarchy for the sake of a concern. Move the concern
  into an advice on the subclasses' methods; the base class shrinks or disappears, and the classes are free to
  extend something else.
- **Replace copied interface implementations with an introduction.** When many classes implement the same small
  interface with identical code (`jsonSerialize()`, `getCreatedAt()`), a trait already removes the duplication.
  Use `#[DeclareParents]` only when the classes must not know about the interface, for example classes of another
  team; otherwise prefer the trait, which is visible in the class.
- **Replace a service call with an advice on its target.** Calls such as `$this->profiler->wrap(fn() => ...)`
  around a method body are an `#[Around]` advice written by hand at each call site.

## Lessons from AspectJ and Spring

Aspect-oriented refactoring has been practised in the Java world for two decades. What carries over:

- **Annotation-driven pointcuts won.** Early AspectJ code selected joinpoints by name patterns, and suffered from
  the fragile pointcut problem: a rename silently removed advice, a new method silently gained it. Spring's
  `@Transactional`, `@Cacheable`, `@Retryable` and `@PreAuthorize`, Micrometer's `@Timed`, Resilience4j and
  jcabi-aspects (`@Loggable`, `@RetryOnFailure`) all mark the method instead. Do the same with attributes. The
  names of those annotations are a good vocabulary: developers know what `#[Transactional]` promises.
- **Refactor in the catalogue's small steps.** The published catalogues of aspect-oriented refactorings (Laddad;
  Monteiro and Fernandes) are built from steps like "Extract Fragment into Advice" and "Extract Feature into
  Aspect", each followed by a test run. They also say when to stop: an aspect that advises one place ("lazy
  aspect") is not worth having.
- **Keep the aspect thin.** Spring's transaction advice delegates to a transaction manager; the advice only decides
  when. Put substantial logic (key building, retry policy, access decisions) into an ordinary service the aspect
  calls. The service is unit-tested like any class, and the advice has almost nothing left to get wrong.
- **Make precedence explicit.** The classic production bugs are orderings nobody chose: a cache outside a security
  check serves data to the wrong user, a retry inside a transaction retries on a dead transaction. Spring has
  `@Order`; here it is the `order` argument. Typical nesting from the outside in: security, rate limit, metrics and
  logging, cache, retry, transaction, the method.
- **An `#[Around]` advice must not swallow exceptions by accident.** A `catch` that logs and forgets to rethrow turns
  failures into `null` results in every advised method at once.
- **Aspects are shared.** One aspect instance serves every advised method, and in long-running workers every
  request. State kept in an aspect (a counter, the "current" user, a memo) is shared state: key it by object or by
  request, or keep it out.
- **Advice on hot paths is paid on every call.** Marking a getter that runs a hundred thousand times per request
  moves the cost of the concern there. Extract at the service boundary.
- **Tell the team.** The oldest objection to AOP is that the code no longer says what it does. Attributes answer
  most of it; a short note in the project documentation, listing the aspects and their attributes, answers the
  rest.

What does *not* carry over from Spring AOP, because Go! AOP weaves the class itself instead of wrapping the object
in a proxy:

- Calls from a method to another method of the same object (`$this->save()`) *are* advised. In Spring they bypass
  the proxy, and much folklore ("`@Transactional` does not work on internal calls") comes from that. Here it means
  an attribute on an inner method takes effect, and that an extracted concern can fire more often than a reader
  used to Spring expects.
- Private, final and static methods, and methods of final classes, can be advised.
- Objects created with `new` are advised like those from the container: there is no "bean" requirement.
