# Refactor: Extract an advice

**Problem.** A fragment of a method does not belong to what the method is about, and an aspect for its concern
exists or is worth creating.

**Solution.** Move the fragment into an advice, and replace it in the method by a marker attribute.

This is the single step that "Extract an aspect" repeats. Use it on its own to add one more method to an existing
aspect, to move an occurrence that differs from the others, or to try a concern on one method before committing to
it.

## Contents

- [Mechanics](#mechanics)
- [From the position of the fragment to the advice type](#from-the-position-of-the-fragment-to-the-advice-type)
- [What an advice can reach](#what-an-advice-can-reach)
- [A fragment in the middle of a method](#a-fragment-in-the-middle-of-a-method)
- [Fragments that resist](#fragments-that-resist)

## Mechanics

1. **Mark the fragment** and classify every variable it touches:
   - read, and it is a parameter, `$this`, a property or a constant: available to an advice;
   - read, and it is a local variable computed earlier in the method: not available, see
     [Fragments that resist](#fragments-that-resist);
   - written, and read by the rest of the method: the fragment produces a value for the business code, so it is not
     a separate concern as it stands.
2. **Find the joinpoint**: the method execution whose boundary the fragment sits at. If the fragment is in the
   middle, create that boundary first ([below](#a-fragment-in-the-middle-of-a-method)).
3. **Choose the advice type** from the position ([below](#from-the-position-of-the-fragment-to-the-advice-type)).
4. **Find or create the aspect** of the concern, and the marker attribute. If an advice for this concern exists,
   compare it with the fragment line by line: a difference becomes an attribute argument, or the fragment is a
   different concern.
5. **Write or extend the advice**, replacing what the fragment took from the method by joinpoint calls.
6. **Add the attribute to the method, delete the fragment**, and run the tests.
7. **Remove dependencies** the method's class no longer uses.

## From the position of the fragment to the advice type

```php
public function export(Report $report): string
{
    $this->limiter->check('export');            // (1) before, may throw      -> #[Before]
    $start = hrtime(true);                      // (2) before, value used in (5)
    try {
        $csv = $this->render($report);          //     the work
    } catch (RenderException $e) {
        $this->alerts->notify($e);              // (3) on failure, rethrows   -> #[AfterThrowing]
        throw $e;
    } finally {
        $this->locks->release('export');        // (4) always                  -> #[After]
    }
    $this->metrics->timing('export', hrtime(true) - $start);   // (5) after, needs (2) -> #[Around] with (2)

    return $csv;
}
```

| Fragment | Advice | Notes |
|----------|--------|-------|
| Statements before the work | `#[Before]` | May read and replace arguments (`setArguments()`), may throw |
| A guard that returns early instead of throwing (`if ($cached) return $cached;`) | `#[Around]` | Only `#[Around]` can skip the work and supply the result |
| Statements after the work on success only | `#[Around]` | `#[After]` also runs on failure; if the original ran only on success, keep that |
| Statements in `finally` | `#[After]` | Runs on success and on failure; it does not see the result or the exception |
| A `catch` that reports and rethrows the same exception | `#[AfterThrowing]` | Receives the exception; cannot swallow or replace it |
| A `catch` that converts, swallows or recovers | `#[Around]` | |
| A pair before and after sharing a value | `#[Around]` | The shared value is a local variable of the advice |
| A loop around the work | `#[Around]` | Each `proceed()` runs the work again |
| Statements that use the result | `#[Around]` | `$result = $invocation->proceed();` |

Two advices of different aspects on one joinpoint run in this order: every `#[Before]`, then the `#[Around]` advices
nested around the work, then `#[AfterThrowing]`, then `#[After]`. Within a kind, a lower `order` runs first.

## What an advice can reach

| In the method | In the advice |
|---------------|---------------|
| A parameter `$orderId` | `$invocation->getArguments()[0]` (by position; trailing arguments the caller omitted are missing) |
| `func_get_args()` | `$invocation->getArguments()` |
| `__METHOD__` | `$m = $invocation->getMethod(); $m->class . '::' . $m->name` |
| `__FUNCTION__` | `$invocation->getMethod()->name` |
| `__CLASS__`, `self::class` | `$invocation->getMethod()->class` |
| `static::class`, `get_class($this)` | `$invocation->getScope()` |
| `$this` | `$invocation->getThis()` (`null` in a static method) |
| A public property or method of `$this` | Through `getThis()` |
| A private or protected member of `$this` | Not reachable. Inject the service into the aspect instead; for state, see below |
| A service held by the class (`$this->logger`) | A constructor dependency of the aspect |
| A constant setting that differed per method | An argument of the marker attribute |
| The return value | `$invocation->proceed()` in an `#[Around]` advice |
| The exception | The second parameter of an `#[AfterThrowing]` advice, or a `catch` in an `#[Around]` advice |
| A local variable | Not reachable |

When a fragment used private state of the object (`$this->tenantId` in a cache key), the options are, in order of
preference: take the value from where the object got it (a service the aspect can also depend on); expose it through
a small interface the class implements (`TenantAware::tenantId()`), which also documents the dependency; or leave
the fragment where it is.

## A fragment in the middle of a method

An advice attaches to the execution of a method, so a fragment between two steps has no joinpoint. Create one with
Extract Method, then advise the new method:

```php
// Before: a retry loop around one call, in the middle of send()
public function send(string $number, string $email): bool
{
    $this->audit->record('send', $number);
    $attempt = 0;
    while (true) {
        try {
            return $this->smtp->deliver($number, $email);
        } catch (\RuntimeException $e) {
            if (++$attempt >= 3) {
                throw $e;
            }
            usleep(1000);
        }
    }
}
```

```php
// After: the call is a method of its own, and the loop is an advice on it
public function send(string $number, string $email): bool
{
    $this->audit->record('send', $number);

    return $this->deliver($number, $email);
}

#[Retry(attempts: 3, delayMs: 1, on: \RuntimeException::class)]
private function deliver(string $number, string $email): bool
{
    return $this->smtp->deliver($number, $email);
}
```

- The new method can be private: private methods are advised like any other, and the call through `$this` is
  intercepted.
- Do the Extract Method as a step of its own, with a test run, before adding the advice.
- Extract the *work*, not the fragment: the method you create is the thing the concern applies to. Its name says
  what it does (`deliver`), not that it is wrapped (`deliverWithRetry`).
- If the call you would extract is already a public method of another class of your code, put the attribute there
  instead, provided every caller should get the behaviour.

## Fragments that resist

| Situation | What to do |
|-----------|------------|
| The fragment reads a local variable computed by the business code before it | Move the fragment, if order does not matter, or extract the business part into a method that returns the value and advise that method |
| The fragment sets a local variable the business code uses afterwards | Not a separate concern in this form. If the value is a resource (a connection, a lock handle), let the business code obtain it from a service, and let the advice manage that service |
| Two fragments of different concerns are interleaved | Reorder them first into nested blocks, test, then extract from the outside in |
| The same fragment, but the copies differ in a condition (`if ($this->debug)`) | Move the condition into the advice, or make it an attribute argument |
| The fragment is inside a loop over items | The joinpoint is the per-item method: extract the loop body into a method and advise it, and mind the cost per call |
| The fragment is in a static method, a closure or a function | Static methods are advised (`getThis()` is `null`). Closures and anonymous classes have no joinpoint: advise the named method that contains them. For functions, see function interception in the `aspect-oriented-development` skill |
| The fragment must see the yielded values of a generator | An advice sees the `Generator` object, not the values: wrap the generator in the advice |
| The method returns by reference | It cannot be advised |
| Removing the fragment would leave an empty method | The method was only the concern. Check whether the callers should call the underlying work directly |
