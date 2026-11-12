# Refactor: Extract an aspect

**Problem.** One concern is implemented by the same code in many methods, and each of those methods does more than
its own job.

**Solution.** Create an aspect for the concern and a marker attribute for the methods. Move the code into an advice,
mark the methods, and delete the code from them.

## Contents

- [Before you start](#before-you-start)
- [Mechanics](#mechanics)
- [Example](#example)
- [Several concerns on the same methods](#several-concerns-on-the-same-methods)
- [Checklist](#checklist)

## Before you start

- The behaviour of the affected methods is pinned down by tests or a characterization script (`SKILL.md`, step 1).
- The framework is installed and its kernel starts before the affected classes are loaded, in every entry point,
  tests included. The classes lie below the include paths, and the kernel runs in debug mode. If one of these is
  missing, set it up with the `aspect-oriented-development` skill first.
- You know every occurrence of the concern, from the scan and from reading (`SKILL.md`, steps 2 and 3).

## Mechanics

1. **List the occurrences and their differences.** Put the copies next to each other. Everything that differs is
   either a setting (the role, the TTL, the message), a quirk to decide on, or evidence that the place does not
   belong to this concern. Occurrences that differ in structure stay out for now; they can follow with
   "Extract an advice".

2. **Create the marker attribute**, in `App\Attribute`, with one constructor parameter per setting and defaults for
   the common case. No behaviour in it.

   ```php
   #[Attribute(Attribute::TARGET_METHOD)]
   final readonly class RequiresRole
   {
       public function __construct(
           public string $role,
       ) {}
   }
   ```

3. **Create the aspect**, in `App\Aspect`: a final class implementing `Go\Aop\Aspect`. Its constructor takes the
   services the scattered code used (the logger, the connection, the cache). Register it the way the project
   registers aspects.

4. **Write the advice from one occurrence.** Copy the code of the most typical occurrence into a public method of
   the aspect with the pointcut `@execution(App\Attribute\<Marker>)`, and adapt it:
   - the business part of the method becomes `$invocation->proceed()` in an `#[Around]` advice, and disappears in
     the other advice types;
   - values that came from the method become joinpoint calls: `__METHOD__`, `func_get_args()`, `$this`
     (`extract-advice.md`, "What an advice can reach");
   - values that differed between the copies are read from the attribute:
     `$invocation->getMethod()->getAttributes(Marker::class)[0]->newInstance()`;
   - services come from the aspect's own properties.

   Choose the advice type by the table in `SKILL.md`, step 3.

5. **Migrate one method.** Add the attribute to it and delete the extracted code from its body. Run the tests. This
   first method proves the advice: if the result differs, look at the advice first, then at what the step changed
   for the other concerns of the same method (see "Several concerns on the same methods").

6. **Migrate the remaining methods one by one**, or in small groups of the same class, running the tests in between.
   Set the attribute arguments from what each copy did.

7. **Verify the matches.** List what the advisor matches (`debug:advisor`, see the `aspect-oriented-development`
   skill) and compare with your list of occurrences: no method missing, none too many.

8. **Clean up the classes.** Remove the constructor parameters, properties and imports that only the extracted code
   used, and update whatever constructs these classes: container configuration, factories, tests.

9. **Run everything once more, and scan again.** The extracted lines no longer show up.

## Example

Two of five service methods that repeat a permission check, a transaction and logging. Before:

```php
class OrderService
{
    public function __construct(
        private Db $db,
        private LoggerInterface $logger,
        private Security $security,
    ) {}

    public function cancel(int $orderId): void
    {
        $this->logger->info('Calling ' . __METHOD__, ['arguments' => func_get_args()]);
        if (!$this->security->isGranted('ROLE_ADMIN')) {
            throw new AccessDenied('ROLE_ADMIN is required');
        }
        $this->db->beginTransaction();
        try {
            if ($orderId <= 0) {
                throw new \InvalidArgumentException('Unknown order');
            }
            $this->db->exec("UPDATE order $orderId SET cancelled");
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error(__METHOD__ . ' failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function total(int $orderId): float
    {
        $this->logger->info('Calling ' . __METHOD__, ['arguments' => func_get_args()]);

        return $orderId * 1.5;
    }
}
```

The scan shows the log call in five methods, and the permission check, `beginTransaction()`, `rollBack()` and the
error record in the same three. Reading shows three concerns: the check is a guard before the work; the transaction
wraps the work; the logging has two parts, the call and the failure, and only the transactional methods log the
failure.

After, the same two methods:

```php
class OrderService
{
    public function __construct(
        private Db $db,
    ) {}

    #[Logged(failures: true)]
    #[RequiresRole('ROLE_ADMIN')]
    #[Transactional]
    public function cancel(int $orderId): void
    {
        if ($orderId <= 0) {
            throw new \InvalidArgumentException('Unknown order');
        }
        $this->db->exec("UPDATE order $orderId SET cancelled");
    }

    #[Logged]
    public function total(int $orderId): float
    {
        return $orderId * 1.5;
    }
}
```

The aspects:

```php
final class LoggingAspect implements Aspect
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Before('@execution(App\Attribute\Logged)', order: 10)]
    public function logCall(MethodInvocation $invocation): void
    {
        $this->logger->info('Calling ' . $this->nameOf($invocation), ['arguments' => $invocation->getArguments()]);
    }

    #[AfterThrowing('@execution(App\Attribute\Logged)')]
    public function logFailure(MethodInvocation $invocation, \Throwable $exception): void
    {
        if ($invocation->getMethod()->getAttributes(Logged::class)[0]->newInstance()->failures) {
            $this->logger->error($this->nameOf($invocation) . ' failed: ' . $exception->getMessage());
        }
    }

    /** What __METHOD__ printed in the original code: the declaring class and the method */
    private function nameOf(MethodInvocation $invocation): string
    {
        $method = $invocation->getMethod();

        return $method->class . '::' . $method->name;
    }
}
```

```php
final class AuthorizationAspect implements Aspect
{
    public function __construct(
        private readonly Security $security,
    ) {}

    #[Before('@execution(App\Attribute\RequiresRole)', order: 20)]
    public function checkRole(MethodInvocation $invocation): void
    {
        $role = $invocation->getMethod()->getAttributes(RequiresRole::class)[0]->newInstance()->role;
        if (!$this->security->isGranted($role)) {
            throw new AccessDenied("$role is required");
        }
    }
}
```

```php
final class TransactionAspect implements Aspect
{
    public function __construct(
        private readonly Db $db,
    ) {}

    #[Around('@execution(App\Attribute\Transactional)')]
    public function transactional(MethodInvocation $invocation): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $invocation->proceed();
            $this->db->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }
}
```

What the example shows:

- **One `try` held two concerns.** The handler rolled back and logged. They are split: the rollback belongs to the
  transaction, the error record to logging. The order is kept: the `#[Around]` advice rolls back and rethrows, then
  the `#[AfterThrowing]` advice logs, as the original did.
- **`order` reproduces the sequence.** The original logged first and checked the role second, so the logging
  `#[Before]` has the lower order. Both run before the transaction opens, because `#[Before]` advices run before
  `#[Around]` advices.
- **A difference became a setting.** Only some methods logged failures: `#[Logged(failures: true)]`. Logging
  failures everywhere would have been a behaviour change.
- **A denied call is not logged as a failure**, in the original (the check was outside the `try`) and in the
  refactored code (an exception from a `#[Before]` advice skips `#[AfterThrowing]`). Had the check been inside
  the `try`, the guard would have to become an `#[Around]` advice to keep that.
- **The class lost two dependencies.** `LoggerInterface` and `Security` left the constructor; the code that creates
  `OrderService` changes with it.

This example was run before and after against the framework: return values, exceptions, the sequence of database
statements and the log records were identical.

## Several concerns on the same methods

When several candidates occur in the same methods, decide first whether they are one concern or several:

- One concern, two halves (`beginTransaction()` and `commit()`/`rollBack()`; a start time and the log line that uses
  it): one `#[Around]` advice.
- Several concerns that guard the same methods: one aspect each, extracted one after the other, with `order` set so
  that the nesting matches the original. Extract the outermost concern first: what remains in the method is then
  still a complete, working body for the next step.

To find the nesting, read one method top to bottom: code that runs earlier and ends later is further out.

A concern can have parts at different depths. In the example the logging has an outer part (the record of the call,
first statement) and an inner part (the record of the failure, inside the `catch`). Extract it in parts, each when
its turn comes: the call record first, then the guard, then the transaction together with the failure record. Had
the whole logging aspect gone first, its `#[AfterThrowing]` advice would have reported the "access denied" of the
guard that was still in the method body, where the original `try` did not cover the guard.

## Checklist

- [ ] Tests or a characterization script cover the side effects of the affected methods
- [ ] Every difference between the copies is a setting, a decision of the user, or excluded
- [ ] Marker attribute and aspect are named after the concern; one aspect per concern
- [ ] Attribute pointcut, unless the rule truly is "everything in this namespace"
- [ ] The weakest advice type that works; an `#[Around]` advice returns `proceed()`
- [ ] `order` set where concerns meet, matching the original sequence
- [ ] Matches of the advisor equal the list of occurrences
- [ ] Unused dependencies removed from the classes, constructions updated
- [ ] Same test results as before; a new scan no longer shows the extracted lines
