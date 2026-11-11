---
name: aspect-oriented-development
description: >-
  Aspect-oriented programming in PHP with `goaop/framework` (Go! AOP). Use it to write or review aspects, pointcut
  expressions and advices (`#[Before]`, `#[After]`, `#[Around]`, `#[AfterThrowing]`, `#[DeclareParents]`), to
  integrate Go! AOP into a Symfony, Laravel or Yii3 application or to set up an `AspectKernel` by hand, to warm up and
  deploy the AOP cache, to answer questions about the performance of aspects, and to debug an advice that does not
  run. Use it whenever a PHP project has `goaop/framework` or one of its bridges in `composer.json` or code under the
  `Go\Aop`, `Go\Core` or `Go\Lang` namespaces, and also when the user wants one behaviour (logging, caching,
  transactions, retries, authorization, rate limits, metrics, auditing) applied across many PHP classes or methods
  without editing each of them, even if they never say "AOP" or "aspect".
---

# Aspect-oriented development with Go! AOP

Go! AOP weaves aspects into PHP classes while they are loaded. It needs no PHP extension and never calls `eval()`:
the woven code is plain PHP in a cache directory.

**Version.** This skill describes the attribute-based API (`Go\Lang\Attribute\*`, `addLazyService()`), which starts
with 4.0. The required PHP version is in the `composer.json` of the package. Older projects (3.x: `registerAspect()`,
protected advices, `dynamic()` pointcuts) use another API: do not apply this skill to them, and follow
`UPGRADE-4.0.md` of the package when the user wants to migrate.

**Documentation of the framework.** The skill is self-contained, and the documentation of the framework goes deeper.
Where a reference file names `docs/<file>.md`, find it here:

- in a project that has the framework installed: `vendor/goaop/framework/docs/`. It matches the installed version,
  so it wins when it disagrees with this skill;
- when this skill is read from a checkout of the framework: `docs/` in the root of that repository;
- otherwise: <https://github.com/goaop/framework/tree/master/docs>.

## Vocabulary

| Term       | Meaning in Go! AOP |
|------------|--------------------|
| Aspect     | A class that implements `Go\Aop\Aspect` and holds advices. One aspect per concern. |
| Joinpoint  | A point the framework can intercept: a method execution, a property read or write, a function call, object creation, the first load of a class. |
| Pointcut   | A string expression that selects joinpoints, e.g. `execution(public App\Service\*->save*(*))`. |
| Advice     | A public method of an aspect with `#[Before]`, `#[After]`, `#[AfterThrowing]` or `#[Around]`. It receives the joinpoint. The woven code refers to it as a first-class callable on the aspect, `The::aspect(LoggingAspect::class)->logCall(...)`, so at runtime an advice is a `Closure` of that method. |
| Weaving    | Rewriting a class so that the selected joinpoints call the advices. |
| Introduction | `#[DeclareParents]`: adds an interface and a trait that implements it to the matching classes. |

## How weaving works, and what follows from it

When Composer loads a class below the include paths, the framework moves the class body into a trait
`<Class>OriginalTrait` and generates a class with the original name that uses the trait. Every advised method is
re-declared there and calls the interceptor chain, then the original body through a private alias. The class keeps its
name, parent and interfaces; no object is wrapped.

Four consequences decide most questions:

1. **Advice applies to every call.** `$this->helper()`, private, static and final methods and methods of final classes
   are intercepted too, because the method itself is replaced. There is no "self-invocation" problem as with proxy
   objects in other AOP tools.
2. **Only code that is woven can be intercepted.** That means classes loaded by Composer *after* the aspect kernel was
   initialized, from files below `appDir` and inside `includePaths`. A class loaded earlier, an internal PHP class or a
   class outside these paths is never changed, and nothing reports it.
3. **Matching happens at weave time, on declarations.** A pointcut sees class names, method names, modifiers,
   attributes and declared return types. It does not see argument values or the runtime type of an object. Decide on
   runtime values inside the advice.
4. **Weaving is cached.** In production mode (`debug` off) the woven classes are served from the cache: an edited
   source file, aspect or pointcut has no effect until the cache is rebuilt. This is the most common reason for "my
   change does nothing". Develop in debug mode.

## Is an aspect the right tool?

An aspect fits a concern that is the same in many places and independent of the business logic around it: logging,
metrics, caching, transactions, retries, circuit breakers, rate limits, authorization checks, audit trails.

Prefer plain code when the behaviour belongs to one or two methods, when the callers must be able to see it, or when it
is business logic. An aspect acts at a distance: someone reading `OrderService::place()` does not see it. Three habits
keep that manageable:

- Select joinpoints with a marker attribute (`#[Transactional]`, `#[Cacheable(ttl: 60)]`) and an `@execution(...)`
  pointcut where possible. The method then shows that something applies to it, and the attribute carries the
  per-method settings.
- Keep name-pattern pointcuts as narrow as the concern, and tell the user which methods a new pointcut matches.
- Keep the contract of the advised method: an advice adds behaviour around the call or fails with an exception. It
  does not make a method return something its signature does not promise.

## Workflow

### Step 0: how is Go! AOP integrated into this application?

Look at `composer.json` before anything else. Most applications run on a framework, and for those the integration is
a ready-made package that initializes the kernel at the right moment, takes aspects from the service container, adds
console commands and builds the cache with the usual deployment commands. Doing this by hand repeats problems the
bridges already solved.

| Application | Use | Then read |
|-------------|-----|-----------|
| Symfony     | `goaop/goaop-symfony-bundle` | `references/frameworks.md` |
| Laravel     | `goaop/goaop-laravel-bridge` | `references/frameworks.md` |
| Yii3        | `goaop/goaop-yii3-bridge`    | `references/frameworks.md` |
| Another framework | Propose a reusable bridge first, see "A framework without a bridge" in `references/frameworks.md` | `references/frameworks.md`, `references/setup.md` |
| Plain PHP, a legacy application, a library | An `AspectKernel` of your own | `references/setup.md` |

If the bridge of the framework is not installed yet, install it instead of writing a kernel.

### Adding an aspect

1. **Write the pointcut.** Read `references/pointcuts.md` when you need more than a simple `execution(...)`:
   wildcards, `|` and `+` have rules that are easy to get wrong.
2. **Write the aspect**: a class implementing `Go\Aop\Aspect`, one public method per advice.
   `references/advices.md` has the joinpoint API and the execution order; `references/recipes.md` has complete,
   tested aspects for the usual concerns.
3. **Register it.** With a bridge, the way the bridge discovers aspects (`references/frameworks.md`); otherwise in
   `configureAop()` of the kernel. An aspect that is not registered is silently ignored.
4. **Verify that it matches.** Do not assume: a pointcut that matches nothing is not an error. The `debug:advisor`
   command lists every member an advisor matches; the advisor id is `<aspect class>-><advice method>`.

   | Integration | Command |
   |-------------|---------|
   | Symfony     | `bin/console debug:advisor --advisor='App\Aspect\LoggingAspect->log'` |
   | Laravel     | no such command: read the woven class, see below |
   | Yii3        | `./yii aop:debug:advisor --advisor='App\Aspect\LoggingAspect->log'` |
   | Own kernel  | `vendor/bin/aspect debug:advisor public/index.php --advisor='App\Aspect\LoggingAspect->log'` |

   Inside a framework the command runs on the kernel the application initialized. Only `vendor/bin/aspect` takes
   the file that initializes the kernel as its first argument, and it needs `symfony/console`. Where no such
   command is available, run the code once in debug mode and read the woven class:
   `references/troubleshooting.md`, "Looking at the woven code".
5. **Run the code** and check the behaviour. In production mode, rebuild the cache first.

### Minimal example

```php
<?php

declare(strict_types=1);

namespace App\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Psr\Log\LoggerInterface;

final class TimingAspect implements Aspect
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Around('execution(public App\Service\**->*(*)) && !execution(public **->__construct(*))')]
    public function measure(MethodInvocation $invocation): mixed
    {
        $start = hrtime(true);
        try {
            return $invocation->proceed();
        } finally {
            $this->logger->debug('{method} took {ms} ms', [
                'method' => (string) $invocation,
                'ms'     => (hrtime(true) - $start) / 1e6,
            ]);
        }
    }
}
```

With a bridge the aspect is an ordinary service of the application: the container injects the logger. With a kernel
of your own it is registered in `configureAop()`:

```php
$container->addLazyService(
    TimingAspect::class,
    static fn(AspectContainer $c): TimingAspect => new TimingAspect($c->getService(LoggerInterface::class)),
);
```

## Rules that are easy to get wrong

These come from the framework, each with its reason. Breaking one either throws while weaving or, worse, does nothing.

- **Advice methods are `public`.** The generated code calls them as first-class callables on the aspect instance.
  A protected or private advice throws `AspectException`. Methods that only carry `#[Pointcut]` may have any
  visibility.
- **One advice attribute per method.** Two of them throw `AspectException`. Write two methods that share a named
  pointcut.
- **The argument list of a pointcut is always `(*)`.** Arguments are not matched; `->save()` and `->save(int)` are
  syntax errors.
- **`*` stays inside one namespace level, `**` crosses levels.** `App\*->*(*)` matches classes directly in `App`
  only; `App\**->*(*)` matches every class below it.
- **Write pointcuts in single-quoted strings**: backslashes need no escaping and `$this->name` is not interpolated.
- **An `#[Around]` advice returns the result.** Return `$invocation->proceed()` unless you replace the result on
  purpose. Without the `return` the caller gets `null`, or PHP throws a `TypeError` ("none returned") when the
  advice declares a return type.
- **`#[After]` and `#[Before]` cannot change the result.** `#[After]` runs like `finally`, also after an exception.
  `#[AfterThrowing]` gets the exception as the second parameter and cannot swallow it. To change a result or to
  handle an exception, use `#[Around]`.
- **An exception thrown by a `#[Before]` advice skips everything else**, the `#[After]` and `#[AfterThrowing]`
  advices of the same joinpoint included.
- **Aspects are never woven.** An advice cannot intercept a method of an aspect, its own included.
- **An aspect must not advise the creation of its own dependencies.** The aspect is built on the first advice call;
  if building it runs one of its advices, the framework throws `AspectCircularReferenceException`.
- **Properties**: `access(...)` works for instance properties. Static, readonly and already hooked properties are
  not intercepted, and a pointcut that only matches those does nothing.
- **The kernel starts before the application classes are loaded**, in every entry point: web, console, workers,
  tests. A bridge takes care of it; with a kernel of your own it is the `init()` call right after
  `vendor/autoload.php`. An entry point without it runs the application without aspects.

## Performance in one paragraph

Aspects cost nothing where they do not apply: a method or property without advice in a woven class runs at full
speed. An intercepted call costs about a third of a microsecond plus the advice itself, and booting the kernel in
production about 0.15 ms per request, measured by the framework's benchmark. Production speed depends on running in
production mode with a prebuilt cache and OPcache. Before answering a performance question or tuning a deployment,
read `references/performance.md`: it has the measured numbers, their conditions and the checklist.

## Reference files

| Read | When |
|------|------|
| `references/frameworks.md` | The application runs on Symfony, Laravel, Yii3 or another framework: installing the bridge, registering aspects, commands, deployment |
| `references/setup.md` | Plain PHP or no bridge: the kernel and its options, entry points, dependencies of aspects, tests, deployment, console commands |
| `references/pointcuts.md` | Writing or reviewing any pointcut that is not trivial |
| `references/advices.md` | Joinpoint methods, execution order, changing arguments and results, properties, functions, `new`, introductions, PHPStan generics |
| `references/recipes.md` | A complete aspect for logging, caching, transactions, retry, authorization, metrics, circuit breaker or rate limiting |
| `references/performance.md` | What aspects cost, production checklist, OPcache, preloading, containers, writing fast aspects |
| `references/troubleshooting.md` | An advice does not run, an exception while weaving, stale cache, limitations |
