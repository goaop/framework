---
name: aspect-oriented-refactoring
description: >-
  Refactors existing object-oriented PHP code into aspects with `goaop/framework` (Go! AOP). Use it when the user
  wants to convert, migrate or clean up existing code with AOP: to find scattered or duplicated cross-cutting code
  (logging, transactions, permission checks, caching, timing, retries, try/catch boilerplate) in source directories,
  to decide which of it belongs in an aspect and with which advice type, and to perform the refactorings "Extract an
  aspect" and "Extract an advice" without changing behaviour. Use it also when the user says the same boilerplate is
  repeated in many methods or services, asks where aspects would help in a codebase, or asks to remove tangled
  infrastructure code from business classes, even if they do not say "AOP". For writing a new aspect from scratch or
  for setting up the framework, use the `aspect-oriented-development` skill instead.
---

# Aspect-oriented refactoring with Go! AOP

This skill turns code that already exists into aspects. The business methods keep what they are about, and the code
that repeated around it moves into one place. It is a refactoring: the observable behaviour stays the same, and each
step is small enough to verify.

It builds on the `aspect-oriented-development` skill of the same plugin, which describes the framework itself:
pointcut syntax, advice types, joinpoints, registration of aspects, framework bridges, troubleshooting. Load that
skill when a step here needs those details, and make sure the framework is installed and its kernel runs before you
extract anything (its "Step 0").

## The two smells

- **Scattering.** One concern is implemented in many places: the same log call, the same permission check, the same
  `try`/`catch` in dozens of methods. Changing the concern means editing all of them.
- **Tangling.** One method implements several concerns: a ten-line method with two lines of business logic between
  logging, a transaction and a cache lookup.

They come together, and an aspect removes both. A concern that is neither scattered nor tangled is not a candidate.

## Workflow

Work through the steps in order. Steps 1 to 4 change nothing and end with a proposal to the user; do not start
editing before they agreed to it.

### 1. Pin down the behaviour

A refactoring is only safe when a change of behaviour would be noticed. Run the test suite and note the result. Where
the methods you are going to touch have no tests, write characterization tests first, or at least a script that calls
them and records everything observable: return values, exceptions, log records, queries, cache entries. The
extracted code is mostly side effects, so a test that only checks return values proves little.

### 2. Scan

Run the bundled scanner over the source directories. It parses the code and reports statements that repeat across
methods, grouped, with their position in the method and a first guess of the concern and the advice type:

```bash
php <skill directory>/scripts/scan-crosscutting.php --min=3 src
```

`--min` is the number of methods a statement has to appear in (default 3); `--format=json` gives the same data for
further processing. The scanner needs `nikic/php-parser` 5 in the `vendor/` of the project, which comes with
`goaop/framework`; `--autoload=FILE` points it at another autoloader.

What it does and does not see:

- It normalizes variable names, strings and numbers, so copies with different messages or role names group together.
- It compares the top-level statements of methods, the handlers of `try` statements (without the body) and loops
  that contain a `try`. Fragments nested deeper, and concerns written differently in each place, need your own
  reading: use `grep` for the vocabulary of a concern (`logger`, `beginTransaction`, `isGranted`, `microtime`).
- "together: in exactly the same methods as #n" marks candidates that may be two halves of one concern, such as
  `beginTransaction()` and the `try`/`catch` with `rollBack()`. It can also be coincidence: two different concerns
  that happen to guard the same methods.
- It also reports repeated business statements (`$this->db->exec(...)` with a different query each time). The
  normalized form looks identical, the meaning is not. Those are not concerns.

### 3. Judge each candidate

Open the reported places and read them. For each candidate decide:

| Question | If not |
|----------|--------|
| Is it the same concern in every place, not just similar text? | Drop it, or split the group |
| Is it independent of the business logic around it? Would the method still be correct, only less observable, guarded or fast, without it? | It is business logic: leave it, or use Extract Method |
| Does it sit at a method boundary: before the work, after it, or around it? | "Extract an advice" has a preparatory step for fragments in the middle |
| Does it only need what a joinpoint offers: the arguments, the object, the result, the exception? | See "What an advice can reach" in `references/extract-advice.md` |
| Is it in three or more places, or certain to spread? | For one or two places an aspect costs more than it saves |

Then name the concern and choose the advice type from where the code sits:

| Where the repeated code sits | Advice |
|------------------------------|--------|
| Only before the work, needs arguments or the object | `#[Before]` |
| Only before the work, and prevents it by throwing (guard, permission check) | `#[Before]` |
| In a `finally`, or after the work whatever the outcome | `#[After]` |
| In a `catch` that reports and rethrows | `#[AfterThrowing]` |
| Before and after with shared state (start time, transaction, lock) | `#[Around]` |
| Uses or replaces the result (cache, conversion) | `#[Around]` |
| Catches and recovers, retries, or skips the work | `#[Around]` |

Prefer the weakest type that does the job: a `#[Before]` advice cannot forget to call `proceed()`.
`references/catalog.md` lists the usual concerns with their code shapes, advice types and pitfalls.

### 4. Propose

Tell the user what you found before changing code: the concerns, where each one occurs (count and files), the aspect
and marker attribute you would create for each, the advice type, what stays in the methods, and anything that would
change behaviour (see "Quirks" below). Recommend an order: the most scattered, least risky concern first, usually
logging or timing; transactions and caching later, since they change control flow.

### 5. Refactor, one concern at a time

Use the two refactorings. Each has its mechanics in a reference file; follow them step by step.

- **Refactor: Extract an aspect.** A concern that is scattered over many methods moves into a new aspect, with a
  marker attribute on the methods. Read `references/extract-aspect.md`.
- **Refactor: Extract an advice.** One fragment of a method, or of a few methods, moves into an advice of an
  existing or new aspect. This is the smaller step, and the building block of the first one. Read
  `references/extract-advice.md`.

After every concern: run the tests or the characterization script, compare with step 1, and commit. Never extract two
concerns in one step: when the output differs, you want one suspect.

### 6. Finish

Run the scanner again: the extracted candidates are gone, and what remains is either the next concern or business
code. Remove what the classes no longer need (see below), and report to the user what moved where.

## Mark the code, do not match it from afar

An aspect acts at a distance: the method no longer shows what happens around it. The remedy is a marker attribute on
the method and an attribute pointcut in the aspect.

```php
#[Transactional]
#[RequiresRole('ROLE_ADMIN')]
public function cancel(int $orderId): void
```

```php
#[Around('@execution(App\Attribute\Transactional)')]
```

- The reader of the method sees every concern that applies, and "find usages" on the attribute lists all the places.
- The attribute carries the settings that differed between the copies: the role, the TTL, the number of attempts.
- A method opts in explicitly. A new method is not caught by accident, and renaming a class or method breaks nothing.
  Name patterns such as `execution(public App\Service\*->save*(*))` fail in both ways; this is known as the fragile
  pointcut problem.

Use a name or namespace pointcut only when the rule really is "everything here, including future code", for
example an audit log for every public method of a repository layer, and say so in the proposal.

Name the attribute after what it promises, not after how the aspect works: `#[Transactional]`, `#[Cacheable]`,
`#[Retry]`, `#[RequiresRole]`, `#[Loggable]`, `#[Timed]`. Put attributes in their own namespace (`App\Attribute`),
aspects in theirs (`App\Aspect`), one aspect per concern.

## Rules for a behaviour-preserving extraction

- **Keep the order.** The original code ran its concerns in a sequence: log, then check, then open the transaction.
  Advices have their own: all `#[Before]` advices run first, then the `#[Around]` advices nest, each kind sorted by
  `order` (lower first, and outermost for `#[Around]`). Set `order` explicitly wherever two extracted concerns meet
  on one method, and write down why.
- **Keep the quirks, or ask.** Copies of scattered code are rarely identical: one logs at another level, one caches
  `null`, one forgets the rollback. Decide per difference: it is a setting (attribute argument), a bug (ask the
  user), or a sign that the place is a different concern (leave it out). Do not silently unify. Extending a concern
  to methods that did not have it is a behaviour change too.
- **Replace what the fragment used.** `__METHOD__` becomes `$method->class . '::' . $method->name` from
  `$invocation->getMethod()`; `func_get_args()` becomes `$invocation->getArguments()`; `$this` becomes
  `$invocation->getThis()`, with access to its public members only.
- **Exceptions thrown by a `#[Before]` advice skip the other advices**, including `#[After]` and
  `#[AfterThrowing]`. A guard that moved into a `#[Before]` advice is therefore not reported by an
  `#[AfterThrowing]` advice. Check that this matches the original order of guard and `try`.
- **Pick the tightest joinpoint.** A retry loop around one call belongs on the method that makes the call, even a
  private one: every method can be advised, and calls through `$this` are intercepted.
- **Clean up afterwards.** Remove constructor parameters, properties and `use` statements the class needed only for
  the extracted concern, and update the places that construct the class (container configuration, factories, tests).
  A service that loses its logger and its security dependency is the visible benefit of the refactoring.

## When not to extract

- The code is business logic, even if it repeats: use ordinary refactorings.
- The copies differ in more than a few settings: the "same" concern is several.
- The fragment needs local variables from the middle of the method, and extracting a method around it would make
  the code worse.
- The callers rely on seeing the behaviour, for instance an explicit transaction boundary that spans several calls
  and is committed by the caller.
- The class cannot be woven: internal classes, code outside the include paths, classes loaded before the kernel.
- The team does not want it. An aspect that the maintainers do not know about is worse than duplication.

## Reference files

| Read | When |
|------|------|
| `references/extract-aspect.md` | Performing "Extract an aspect": mechanics, a complete before/after example, checklist |
| `references/extract-advice.md` | Performing "Extract an advice": fragment position to advice type, what an advice can reach, fragments in the middle of a method, fragments that cannot be extracted |
| `references/catalog.md` | Recognizing a concern: code shapes, advice type, attribute, pitfalls; related refactorings (introductions, replacing decorators and base classes); lessons from AspectJ and Spring |
