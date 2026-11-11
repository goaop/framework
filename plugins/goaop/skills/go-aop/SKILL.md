---
name: go-aop
description: Aspect-oriented programming in PHP with goaop/framework (Go! AOP) 4.x. Use it to write or review aspects, pointcut expressions and advices (#[Before], #[After], #[Around], #[AfterThrowing], #[DeclareParents]), to set up or configure an AspectKernel, to warm up and deploy the AOP cache, and to debug an advice that does not run. Use it whenever a PHP project has goaop/framework in composer.json or code under the Go\Aop, Go\Core or Go\Lang namespaces, and also when the user wants one behaviour (logging, caching, transactions, retries, authorization, metrics, auditing) applied across many PHP classes or methods without editing each of them, even if they never say "AOP" or "aspect".
---

# Go! AOP: aspect-oriented programming in PHP

Go! AOP weaves aspects into PHP classes while they are loaded. It needs no PHP extension and never calls `eval()`:
the woven code is plain PHP in a cache directory.

This skill covers `goaop/framework` **4.x** and newer; the required PHP version is in its `composer.json` (8.4+
for 4.x). Check the installed version first: `composer show goaop/framework`, or for a dev install look for
`vendor/goaop/framework/UPGRADE-4.0.md`, which exists from 4.0 on. On 3.x the API differs (`registerAspect()`,
protected advices, `dynamic()` pointcuts): do not apply this skill to 3.x code, and follow that upgrade guide when
the user wants to migrate.

The package ships its documentation in `vendor/goaop/framework/docs/`. It matches the installed version exactly, so
read it when a detail here is not enough or seems to disagree with what you observe.

## Vocabulary

| Term       | Meaning in Go! AOP |
|------------|--------------------|
| Aspect     | A class that implements `Go\Aop\Aspect` and holds advices. One aspect per concern. |
| Joinpoint  | A point the framework can intercept: a method execution, a property read or write, a function call, object creation, the first load of a class. |
| Pointcut   | A string expression that selects joinpoints, e.g. `execution(public App\Service\*->save*(*))`. |
| Advice     | A public method of an aspect with `#[Before]`, `#[After]`, `#[AfterThrowing]` or `#[Around]`. It receives the joinpoint. |
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
2. **Only code that is woven can be intercepted.** That means classes loaded by Composer *after* `init()`, from files
   below `appDir` and inside `includePaths`. A class loaded earlier, an internal PHP class or a class outside these
   paths is never changed, and nothing reports it.
3. **Matching happens at weave time, on declarations.** A pointcut sees class names, method names, modifiers,
   attributes and declared return types. It does not see argument values or the runtime type of an object. Decide on
   runtime values inside the advice.
4. **Weaving is cached.** With `'debug' => false` the woven classes are served from the cache: an edited source
   file, aspect or pointcut has no effect until the cache is rebuilt. This is the most common reason for "my change
   does nothing". Develop with `'debug' => true`.

## Is an aspect the right tool?

An aspect fits a concern that is the same in many places and independent of the business logic around it: logging,
metrics, caching, transactions, retries, authorization checks, audit trails, deprecation notices.

Prefer plain code when the behaviour belongs to one or two methods, when the callers must be able to see it, or when it
is business logic. An aspect acts at a distance: someone reading `OrderService::place()` does not see it. Two habits
keep that manageable:

- Select joinpoints with a marker attribute (`#[Transactional]`, `#[Cacheable(ttl: 60)]`) and an `@execution(...)`
  pointcut where possible. The method then shows that something applies to it, and the attribute carries the
  per-method settings.
- Keep name-pattern pointcuts as narrow as the concern, and tell the user which methods a new pointcut matches.

## Workflow

### Adding an aspect

1. **Find the kernel.** Search for a class that extends `Go\Core\AspectKernel` and for the call of `->init(`. If there
   is none, set the framework up first: `references/setup.md`.
2. **Write the pointcut.** Read `references/pointcuts.md` when you need more than a simple `execution(...)`:
   wildcards, `|` and `+` have rules that are easy to get wrong.
3. **Write the aspect**: a class implementing `Go\Aop\Aspect`, one public method per advice.
   `references/advices.md` has the joinpoint API and the execution order; `references/recipes.md` has complete,
   tested aspects for the usual concerns.
4. **Register it** in `configureAop()` of the kernel. An aspect that is not registered is silently ignored.
5. **Verify that it matches.** Do not assume: a pointcut that matches nothing is not an error.

   ```bash
   vendor/bin/aspect debug:advisor public/index.php --advisor='App\Aspect\LoggingAspect->logCall'
   ```

   The argument is the file that initializes the kernel. The command lists every member the advisor matches; the
   advisor id is `<aspect class>-><advice method>`. The console needs `symfony/console`. Without it, run the code
   once with `'debug' => true` and check the woven class instead: `references/troubleshooting.md`, "Looking at the
   woven code".
6. **Run the code** and check the behaviour. With `'debug' => false`, rebuild the cache first.

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

    #[Around('execution(public App\Service\*->*(*))')]
    public function measure(MethodInvocation $invocation): mixed
    {
        $start = hrtime(true);
        try {
            return $invocation->proceed();
        } finally {
            $this->logger->debug($invocation . ' took ' . (hrtime(true) - $start) / 1e6 . ' ms');
        }
    }
}
```

```php
// In the kernel
protected function configureAop(AspectContainer $container): void
{
    $container->addLazyService(
        TimingAspect::class,
        static fn(AspectContainer $c): TimingAspect => new TimingAspect($c->getService(LoggerInterface::class)),
    );
}
```

`getService(LoggerInterface::class)` is answered by the PSR-11 container of the application once it is attached with
`setDelegate()`. In an application without a container, build the dependency in the factory or register it as a
service of the aspect container. Both are shown in `references/setup.md`.

## Rules that are easy to get wrong

These come from the framework, each with its reason. Breaking one either throws while weaving or, worse, does nothing.

- **Advice methods are `public`.** The generated code calls them as first-class callables on the aspect instance
  (`The::aspect(X::class)->method(...)`). A protected or private advice throws `AspectException`. Methods that only
  carry `#[Pointcut]` may have any visibility.
- **One advice attribute per method.** Two of them throw `AspectException`. Write two methods that share a named
  pointcut.
- **The argument list of a pointcut is always `(*)`.** Arguments are not matched; `->save()` and `->save(int)` are
  syntax errors.
- **`*` stays inside one namespace level, `**` crosses levels.** `App\*->*(*)` matches classes directly in `App`
  only; `App\**->*(*)` matches every class below it.
- **Write pointcuts in single-quoted strings**: backslashes need no escaping and `$this->name` is not interpolated.
- **An `#[Around]` advice returns the result.** Return `$invocation->proceed()` unless you replace the result on
  purpose. Without the `return` the caller gets `null`, or PHP throws a `TypeError` ("none returned") when the
  advice declares a return type. Calling `proceed()` twice runs the method twice
  (wanted for a retry, a bug elsewhere).
- **`#[After]` and `#[Before]` cannot change the result.** `#[After]` runs like `finally`, also after an exception.
  `#[AfterThrowing]` gets the exception as the second parameter and cannot swallow it. To change a result or to
  handle an exception, use `#[Around]`.
- **Aspects are never woven.** An advice cannot intercept a method of an aspect, its own included.
- **An aspect must not advise the creation of its own dependencies.** The aspect is built on the first advice call;
  if building it runs one of its advices, the framework throws `AspectCircularReferenceException`.
- **Properties**: `access(...)` works for instance properties. Static, readonly and already hooked properties are
  not intercepted, and a pointcut that only matches those does nothing.
- **An exception thrown by a `#[Before]` advice skips everything else**, the `#[After]` and `#[AfterThrowing]`
  advices of the same joinpoint included.
- **Call `init()` once, right after `vendor/autoload.php`, in every entry point**: web front controller, console
  script, worker, test bootstrap. An entry point without it runs the application without aspects.

## Reference files

| Read | When |
|------|------|
| `references/setup.md` | Installing, the kernel and its options, entry points, dependencies from the application container, tests, production deployment, console commands |
| `references/pointcuts.md` | Writing or reviewing any pointcut that is not trivial |
| `references/advices.md` | Joinpoint methods, execution order, changing arguments and results, properties, functions, `new`, introductions, PHPStan generics |
| `references/recipes.md` | A complete aspect for logging, caching, transactions, retry, authorization, deprecation, fluent setters or magic methods |
| `references/troubleshooting.md` | An advice does not run, an exception while weaving, stale cache, limitations |
