# Troubleshooting

Limitations in full: `docs/php84-limitations.md`, `docs/php85-limitations.md` and the "Limitations" section of
`docs/advices.md` of the framework (where to find them: `SKILL.md`, "Documentation of the framework").

## Contents

- [The advice does not run](#the-advice-does-not-run)
- [A change has no effect](#a-change-has-no-effect)
- [Exceptions](#exceptions)
- [Looking at the woven code](#looking-at-the-woven-code)
- [What cannot be intercepted](#what-cannot-be-intercepted)

## The advice does not run

Nothing reports a pointcut without matches or a class that was not woven, so go through the chain in this order. Each
step has a check that takes seconds. Several causes often hide behind each other: after every fix, delete the cache
directory, run again and restart the chain.

Steps 1 and 2 use the debug commands. How they are called depends on the integration:

| Integration | Command | Loader argument |
|-------------|---------|-----------------|
| Symfony bundle | `bin/console debug:aspect`, `bin/console debug:advisor` | none: the commands run on the kernel of the application |
| Yii3 bridge | `./yii aop:debug:aspect`, `./yii aop:debug:advisor` | none |
| Laravel bridge | no debug commands | — |
| Kernel of your own | `vendor/bin/aspect debug:aspect <loader>`, `vendor/bin/aspect debug:advisor <loader>` | the file that initializes the kernel, e.g. `public/index.php`; needs `symfony/console` |

Without the commands, read the registration of the aspects for step 1 and go on with step 3, which needs no
tooling; then look at the woven class as described in [Looking at the woven code](#looking-at-the-woven-code). In a
Symfony application the Go! AOP panel of the web profiler shows the aspects, the woven classes of the request and the
classes that were loaded before weaving was possible.

1. **Is the aspect registered?**

   ```bash
   vendor/bin/aspect debug:aspect public/index.php
   ```

   The aspect and its advisors must be listed. If not: register it the way the integration expects (a tagged or
   autoconfigured service, the config list of the bridge, or `configureAop()` of your own kernel), and make sure the
   class implements `Go\Aop\Aspect` and the method has an advice attribute from `Go\Lang\Attribute` (check the `use`
   statements: an attribute class that does not exist is ignored by PHP without an error).

2. **Does the pointcut match the member?**

   ```bash
   vendor/bin/aspect debug:advisor public/index.php --advisor='App\Aspect\LoggingAspect->logCall'
   ```

   It prints `-> matching method ...` lines. No line for the method: the expression is wrong. The usual causes are
   in `pointcuts.md`, "Common mistakes": `*` instead of `**`, `->` for a static method, a wrong visibility, a class
   name that is not fully qualified, an interface name without `+`.

3. **Is the class woven at runtime?** Ask PHP in the failing entry point:

   ```php
   var_dump(is_subclass_of(App\Service\OrderService::class, Go\Aop\Proxy::class));   // true when woven
   var_dump((new ReflectionClass(App\Service\OrderService::class))->getFileName());
   ```

   `false` although step 2 matched means the class was loaded without weaving:
   - The kernel is not started in this entry point (console script, worker, test bootstrap), or is started after the
     class was already loaded. With a kernel of your own, move `init()` directly behind `vendor/autoload.php`. With
     a bridge, check its requirement: first bundle in `config/bundles.php` (Symfony), the `AopBootstrap::init()` line
     in every entry script (Yii3); classes the framework loads before the bridge boots stay unwoven.
   - The file is outside `includePaths` or below `excludePaths`, or outside `appDir`.
   - The class is not loaded by Composer: a manual `require`, another autoloader, an `autoload.files` entry, or
     OPcache preloading of the original file. For includes use `Features::INTERCEPT_INCLUDES`.
   - The cache is stale, see the next section. The cache also remembers the files that needed no weaving, so with
     `'debug' => false` a class can stay unwoven after an aspect for it was added, while classes the cache has never
     seen are woven. Aspects that work for some classes and not for others point to the cache.

4. **Is the call one that can be intercepted?** See [What cannot be intercepted](#what-cannot-be-intercepted).

5. **Does the advice run but have no visible effect?** A `#[Before]` or `#[After]` advice cannot change the result;
   an `#[Around]` advice without `return` yields `null` (or a `TypeError`, see below); an advice that throws inside a `try` of the caller may be
   swallowed there. An `#[After]` or `#[AfterThrowing]` advice is skipped when a `#[Before]` advice of the same
   joinpoint threw.

## A change has no effect

| Situation | Cause | Fix |
|-----------|-------|-----|
| `'debug' => false`, a source file, aspect or pointcut was edited | Woven classes are served from the cache; changes are only tracked in debug mode | Delete the cache directory or run `cache:warmup:aop`; develop with `'debug' => true` |
| `Features::PREBUILT_CACHE`, any file was edited | Nothing is checked at runtime | Rebuild the cache on every deployment |
| The advice body was edited, and the old one still runs | OPcache with `validate_timestamps=0` serves the old aspect file | Reset OPcache (restart PHP-FPM) |
| Works in the browser, not on the command line (or the reverse) | The entry points pass different options or one has no `init()` | Share one `config/aop.php` |

When in doubt, delete the cache directory: it is always safe, the cache is rebuilt. Do it as well when switching an
existing cache between `debug` modes.

## Exceptions

| Message or type | Cause | Fix |
|-----------------|-------|-----|
| `Advice method X::m() must be public` (`AspectException`) | Protected or private advice | Make it public |
| `UnexpectedTokenException: Unexpected ... at line 1`, `PointcutSyntaxException` | Invalid pointcut expression: empty `()`, missing visibility, unbalanced parentheses | Fix the expression; the message names the position and the expected tokens |
| `TypeError: X::advice(): Return value must be of type mixed, none returned` | An `#[Around]` advice without `return` | `return $invocation->proceed();` |
| `Unknown kernel option "x"` (`InvalidConfigurationException`) | Typo or a 3.x option | Use the options listed in `setup.md` |
| `WeavingException: ... failed to transform <file>` | Wraps the real error for that file: read the previous exception | Fix the cause; common ones are below |
| `WeavingException` about a method returning by reference | `function &name()` matched by a pointcut | Add `&& !matchReturningByReference()` |
| `WeavingException` about `#[\Override]` on a trait method | A class imports the method from a trait and the pointcut matches it there | Add `&& !matchInherited()`, or advise the trait itself |
| `... is missing from the prebuilt AOP cache` (`WeavingException`) | `PREBUILT_CACHE` and a class that was added after the build | Run `cache:warmup:aop` |
| `ServiceNotFoundException` when an advice runs | The aspect or one of its dependencies is not registered, or the advice ran before `setDelegate()` | Register it; attach the application container earlier or narrow the pointcut |
| `AspectCircularReferenceException` | Building the aspect calls a method the aspect advises | Inject that dependency lazily, or exclude it from the pointcut |
| `NotCompilableException` | An advice given as a closure that is not a method of an aspect | Declare the advice as a public aspect method |
| `Call to undefined method ...::registerAspect()` | Code written for 3.x | `add()` / `addLazyService()`; see `UPGRADE-4.0.md` |
| Cache directory not writable | Web server user and deploy user differ | Fix permissions (`cacheFileMode`), or build the cache at deploy time with `PREBUILT_CACHE` |

Every exception of the framework implements `Go\Aop\Exception\ExceptionInterface`.

## Looking at the woven code

The cache mirrors the source tree below `cacheDir`. For `src/Service/OrderService.php`:

| File | Content |
|------|---------|
| `<cacheDir>/src/Service/OrderService.php` | The generated class: the advised methods and their interceptor lists |
| `<cacheDir>/src/Service/OrderServiceOriginalTrait.php` | The original class body as a trait |
| `<cacheDir>/src/Aspect/LoggingAspect.cache.php` | The compiled advisors of the aspect |
| `<cacheDir>/_functions/<Namespace>.php` | Function proxies of a namespace |
| `<cacheDir>/_include.cache`, `_transformation.cache` | Metadata: which source file maps to which cached file, and which files were left untransformed |

Reading the generated class answers "which advices apply to this method, in which order" directly:

```php
static $__joinPoint = InterceptorInjector::forMethod(
    self::class,
    'place',
    [
        Interceptor::before(The::aspect(AuthorizationAspect::class)->checkRole(...), order: -100),
        Interceptor::around(The::aspect(CachingAspect::class)->cache(...)),
    ],
    $this->placeOriginalAlias(...),
);
```

A method that is not re-declared there is not advised. A class without an `...OriginalTrait.php` file in the cache
was not woven at all: no pointcut matched it when the cache was built. Never edit files in the cache: they are regenerated.

Breakpoints work in aspects, in the original source files and in the generated code. Stack traces show the generated
method, the joinpoint and the `...OriginalAlias` call between the caller and the original method.

## What cannot be intercepted

| Case | Why | Alternative |
|------|-----|-------------|
| Classes outside `includePaths`, internal PHP classes | They are not woven | Add the directory to `includePaths`; advise your own wrapper class |
| Methods of aspects | Aspects are never woven | Move the code into a service |
| Methods returning by reference | The joinpoint would return a copy | Exclude with `!matchReturningByReference()` |
| Trait methods with `#[\Override]`, in the class importing them | PHP copies the attribute onto the alias | Advise the trait, or exclude with `!matchInherited()` |
| Static, readonly or hooked properties; inherited private or final ones | Interception uses property hooks, which PHP does not allow there | Advise the methods that change the property |
| Property accesses by reflection or ORM hydration | They bypass hooks | — |
| Functions called with a leading `\` or through `use function` | The call no longer resolves in the namespace | Call them unqualified in the code to intercept |
| Function calls from code in the global namespace | There is no namespace to declare the proxy in | Move the code into a namespace |
| `new` in files that are not woven | The expression is not rewritten | Advise the constructor with `execution(public Foo->__construct(*))` |
| Values yielded by a generator | The advice runs at the call, the values come later | Wrap the returned generator in the advice |
| Methods that do not exist (magic calls) | Nothing to match | Advise `__call` / `__callStatic` |
| Anonymous classes and closures | They have no name to match | Advise the named method that contains them |
