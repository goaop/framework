Changelog
======
4.0.0 (unreleased)

See [UPGRADE-4.0.md](UPGRADE-4.0.md) for the migration guide.

**Requirements**
* [BC BREAK] Requires PHP 8.4+
* [BC BREAK] **Dependencies** — `ext-tokenizer`, `goaop/parser-reflection` ^4.0, `goaop/dissect` ^4.0 and `symfony/finder` ^7.4 || ^8.0. `nikic/php-parser` 5 comes through parser-reflection and is no longer required directly. `symfony/console` ^7.4 || ^8.0 is needed for the `bin/aspect` tool.

**Added**
* [Feature] **First-class callable advices** — the main way advices are now wired into woven code. Generated proxies declare each aspect-method advice as `Interceptor::before(The::aspect(MonitorAspect::class)->beforeMethodExecution(...))` — an eager first-class callable, since the interceptor list is only built while the intercepted method or hook is already executing (`LazyAdvisorAccessor` is removed). Compiled advisor cache files use the same facade in its lazy static-data form, e.g. `Interceptor::before(MonitorAspect::class, 'beforeMethodExecution')`, which returns a native PHP lazy proxy: interceptor construction, aspect resolution from the container and advice callable creation are all deferred until the advice is actually used, so cached advices whose pointcut never matches never instantiate their aspect. Advices registered in the container as plain closures (not aspect methods) are resolved through the new `The::advice('advisorId')` accessor, which unwraps `Advisor` and interceptor values down to the raw advice closure.
* [Feature] **Private method interception** — both dynamic (`private function foo()`) and static (`private static function bar()`) private methods can now be intercepted by aspects. This was impossible with the old extend-based engine because PHP does not allow overriding private methods in subclasses.
* [Feature] **Property interception with native PHP 8.4 property hooks** — `access(...)` pointcuts intercept reads and writes of public, protected and private instance properties, including constructor-promoted properties and properties declared in traits. The proxy re-declares every intercepted property with `get`/`set` hooks that dispatch through `FieldAccess` joinpoints (by reference for arrays, so indirect modification keeps working); asymmetric visibility (`private(set)`, `protected(set)`) and property attributes are preserved. Readonly, static and already-hooked properties are never intercepted, see `docs/php84-limitations.md`.
* [Feature] **PHP 8.1+ enum interception** — instance and static methods on both unit (pure) and backed enums can now be intercepted by aspects. The enum body is extracted into a trait (`FooOriginalTrait`); a proxy enum re-declares the cases and dispatches intercepted methods via per-method `static $__joinPoint` caching. Built-in enum methods (`cases`, `from`, `tryFrom`) and initialization joinpoints are never woven.
* [Feature] **PHP 8.5+ first-class callable default values** — proxy generation for method parameters and constructor-promoted properties now supports `Closure` default values expressed via first-class callable syntax (e.g., `function foo($cb = strlen(...))`). The raw AST expression node is preserved through the proxy-generation pipeline so the generated source code reproduces the original FCC default verbatim.
* [Feature] **PHP 8.5 support** — the framework is tested on PHP 8.4 and 8.5; supported 8.5 syntax and the known limitations are listed in `docs/php85-limitations.md`.
* [Feature] **Generic types for static analysis** — the core joinpoint interfaces (`Joinpoint`, `Invocation`, `MethodInvocation`, `ConstructorInvocation`, `FieldAccess`, `FunctionInvocation`, ...) declare `@template` types for the target class and the return value, so PHPStan and IDEs know the types of `getThis()`, `proceed()` and friends inside advices.
* [Feature] **Attributes are propagated to proxies** — attributes of the woven class, its methods, parameters, constants and properties are copied from the source AST (arguments included, not evaluated values), so runtime attribute inspection of a proxy returns the original attributes (e.g. `#[\Deprecated]`).
* [Feature] [BC BREAK] **Interface-keyed registration listeners** — new `AspectContainer::onRegistration(string $interfaceFQCN, Closure $listener)`: the listener receives the id (class-name) and the container whenever a deferred service whose id implements the interface is registered via `addLazyService()`. Values are never touched, so laziness is preserved; with no listeners armed (production) registration stays a pure array write. The kernel arms one `Aspect::class` listener in debug mode to track aspect source files as freshness resources at registration time (previously hardwired into `registerAspect()`). `AspectContainer::addResource()` is now part of the interface and `final public` on `Container` (was `final protected`).
* [Feature] Console commands declare their name, description and help with `#[AsCommand]`, `bin/aspect` loads them lazily, and every command returns `Command::SUCCESS`/`Command::FAILURE` (`debug:weaving` used to return the raw error count). `cache:warmup:aop` stops cleanly on SIGINT/SIGTERM.
* [Feature] [BC BREAK] **Doctrine bridge rewritten for the trait-based engine (#671)** — new `Go\Bridge\Doctrine\WovenEntityClassLocator` lets Doctrine mapping drivers (ORM 3.6+) discover entities through the autoloader, so the woven class is mapped instead of the original source, and entities loaded before discovery are no longer missed. `MetadataLoadInterceptor` no longer rewrites metadata (the old mapped-superclass workaround only matched the extend-based engine); it now reports woven entities with intercepted mapped properties when Doctrine native lazy objects are disabled. It is registered as a plain listener: `$eventManager->addEventListener(Events::loadClassMetadata, new MetadataLoadInterceptor())` (it is no longer an `EventSubscriber`).

**Changed**
* [BC BREAK] Proxy engine switched from inheritance-based to **trait-based**: the original class body is converted to a PHP trait (`FooOriginalTrait`) and the proxy class uses it via `use` with private method aliases instead of extending the renamed class. This removes the renamed `<Class>OriginalTrait` parent from the inheritance chain.
* [BC BREAK] Generated code no longer uses the `__aop__` prefix. The trait holding the original class body is now suffixed with `OriginalTrait` (`FooOriginalTrait` instead of `Foo__AopProxied`) and the private trait aliases that back the original method bodies carry their own `OriginalAlias` suffix (`doSomethingOriginalAlias` instead of `__aop__doSomething`). Accordingly `AbstractMethodInvocation::TRAIT_ALIAS_PREFIX` is replaced by `AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX` (`'OriginalAlias'`), `AspectContainer::ORIGINAL_TRAIT_SUFFIX` is `'OriginalTrait'`, and the hooks declared by `InitializationAware` and `StaticInitializationAware` are renamed to `__initialization()` and `__staticInitialization()`. Cached proxies generated by an earlier 4.0 development version must be regenerated.
* [BC BREAK] **Aspect advice methods must be public.** Because generated proxies call advices as first-class callables on the aspect instance, an advice method annotated with `#[Before]`, `#[After]`, `#[Around]` or `#[AfterThrowing]` can no longer be `protected` or `private` — the aspect loader now throws an `AspectException` for non-public advice methods. Methods holding only a `#[Pointcut]` attribute may keep any visibility.
* [BC BREAK] Removed the `AdviceBefore`, `AdviceAfter` and `AdviceAround` marker interfaces. The `Advice` interface now requires `getType(): AdviceTypeEnum`, and the new `AdviceTypeEnum` backed enum (`Before`, `After`, `AfterThrowing`, `Around`, `Introduction`) carries both the advice kind and its invocation priority used for joinpoint sorting.
* [BC BREAK] All invocation class constructors (`DynamicTraitAliasMethodInvocation`, `StaticTraitAliasMethodInvocation`, `ReflectionFunctionInvocation`) now require a `Closure $closureToCall` parameter (non-nullable). Generated proxy code always passes a first-class callable: `$this->methodOriginalAlias(...)` for own instance methods, `self::methodOriginalAlias(...)` for own static methods, `parent::method(...)` for inherited methods, and `\functionName(...)` for functions.
* [Feature] `self::` in proxied classes now resolves to the proxy class naturally (via PHP trait semantics), removing the need for `SelfValueTransformer`.
* [Feature] **First-class callable syntax** — generated proxy code and invocation constructors use PHP 8.1+ first-class callable syntax (`$this->methodOriginalAlias(...)`, `parent::method(...)`, `\func(...)`) to reference original method and function bodies, eliminating the need for `Closure::bind` at construction time.
* [BC BREAK] **Own code generator** — proxies are generated by the internal `Go\Proxy\Generator` package built on nikic/php-parser instead of laminas/laminas-code, which is no longer a dependency. Code that extended the framework generators or relied on laminas generator classes must be updated.
* [BC BREAK] The `?` single-character wildcard is removed from pointcut name and type patterns: it collided with the nullable type syntax. In return-type patterns a leading `?` is now a nullable marker (`?Foo` equals `Foo|null`). Replace `?` wildcards with `*`.
* [BC BREAK] `ModifierPointcut` is final and immutable: the masks are constructor arguments and `andMatch()`/`orMatch()`/`notMatch()` return a new instance instead of modifying the pointcut.
* [BC BREAK] **Flat cache layout** — the `_proxies` subdirectory is gone: a proxy is stored at the relative path of its source file below the cache directory, and the woven source (the `...OriginalTrait` body) next to it with the `OriginalTrait` suffix. Clear the cache directory when upgrading.
* [BC BREAK] **Exception hierarchy** — every exception thrown by the framework implements `Go\Aop\Exception\ExceptionInterface`. New types keep the SPL parent of the throw sites they replace where possible: `InvalidConfigurationException` (kernel options, container registrations, console arguments; an `InvalidArgumentException`), `PointcutSyntaxException` (invalid pointcut expressions; an `AspectException`), `ServiceNotFoundException` (an `OutOfBoundsException`), `WeavingException` (transformation, proxy generation and cache writes; a `RuntimeException`), and `NotCompilableException`, now public in `Go\Aop\Exception`. `AspectKernel::getContainer()` before `init()` throws a clear `AspectException`.
* [BC BREAK] **Final classes** — the container, loaders, transformers, the Enum/Trait/Function proxy generators and the advice attributes are `final`; framework-internal services are marked `@internal`.
* [BC BREAK] **Removed unused API** — the `#[Aspect]` attribute, `Pointcut::KIND_TRAIT` (`KIND_ALL` is `119`), `InterceptedConstructorGenerator`, `FunctionParameterList` and a few unused methods; see UPGRADE-4.0.
* [BC BREAK] **Pointcut semantics** — space-separated modifiers must all match and `|` alternatives bind tighter, so `final public|protected` no longer matches every non-final public method. `Foo+` also matches `Foo` itself and classes using the trait `Foo`. `@execution`/`@within`/`@access` match subclasses of the attribute. Return-type patterns accept the `?Foo` nullable marker, and regular-expression failures raise a `PointcutSyntaxException` instead of silently not matching.
* [BC BREAK] **Methods returning by reference** can not be intercepted: weaving one fails with a `WeavingException` instead of silently returning a copy. The new `matchReturningByReference()` pointcut excludes them (`&& !matchReturningByReference()`).
* [BC BREAK] **Renames** — `TransformerResultEnum::RESULT_*` becomes `TransformerResult::{Aborted, Abstain, Transformed}`; enum cases use PascalCase everywhere (`FieldAccessType::Read`/`Write`, and the `Proxy\Generator` enums `Visibility`, `PropertyModifier`, `ClassModifier`); `AspectContainer::AOP_PROXIED_SUFFIX` becomes `ORIGINAL_TRAIT_SUFFIX` plus `ORIGINAL_TRAIT_FILE_SUFFIX`.

**Performance**
* [Performance] **Direct static joinpoint initialization** — leveraging PHP 8.3+ support for dynamic expressions in static variable initializers, all generated proxy method bodies now initialize their static joinpoint variables directly.
* [Performance] [BC BREAK] **Truly lazy container services** — `AspectContainer::addLazyService()` stores a factory instead of building a PHP 8.4 lazy proxy, so registering the built-in services no longer reflects/autoloads them on every request; a service is constructed on first retrieval. Code relying on lazy-proxy instances being available from boot must use `getService()` instead.
* [Performance] [BC BREAK] **Aspects are typical container services; `registerAspect()` removed** — the container is now a fully AOP-unaware DI implementation. Register aspects in `configureAop()` through the generic API: `$container->add(Foo::class, new Foo())` for an eager instance, or `$container->addLazyService(Foo::class, static fn() => new Foo(...))` for deferred construction on first use (first advice hit or first real interaction with the lazy object). `AspectContainer::registerAspect()`, the internal per-aspect validators and the double per-aspect validation pass are gone; a factory returning an incompatible object now fails on first use via the container's `instanceof` guard instead of at retrieval. Aspect enumeration on the weaving path is unchanged (`getServicesByInterface(Aspect::class)` finds deferred aspects by id).
* [Performance] [BC BREAK] **Class-keyed runtime cache map, integrated into composer** — the weaver records the FQCN of every discovered class into the cache metadata, and the runtime cache file `_include.cache` holds a woven-class => cached-file map plus a skip set of known untransformed classes. At production boot the class map is handed to composer via `ClassLoader::addClassMap()`, so woven classes resolve natively to their cached files (composer consults the class map before PSR-4) with no per-class `realpath()`; untransformed classes are served untouched. `_transformation.cache` keeps the full build metadata and is loaded lazily, only on the cache-miss/weaving paths. **Cache format bump**: a pre-4.0 cache directory carries no class names and is treated as stale — everything re-weaves once (or run `cache:warmup:aop` at deploy). New accessors: `CachePathManager::queryClassMap()`, `querySkippedClasses()`, `registerClassForResource()`.
* [Performance] [BC BREAK] **`PREBUILT_CACHE` now really trusts the cache** — with `Features::PREBUILT_CACHE` enabled, an existing cache record is used without any freshness checks: cache directory existence/writability probes, source `filemtime` comparisons, tracked-resource checks and advisor cache freshness are all skipped (previously the flag only skipped one writability check). Build the cache at deploy time (`bin/aspect cache:warmup:aop`); staleness is the deployer's responsibility. An advisor cache file of an incompatible (older) format falls back to the direct loader without writing (read-only file systems stay safe); a corrupt, non-includable file throws.
* [Performance] [BC BREAK] **Lazy transformation pipeline** — every source transformer is a deferred container service, tagged by the `SourceTransformer` interface and assembled in registration order; the stream filter and the transformers are only registered/constructed on the first cache miss (`SourceTransformingLoader::ensureRegistered()`), never on a warm-cache request. The protected `AspectKernel::registerTransformers()` hook (returning transformer instances, used e.g. by AspectMock to swap in its own weaver) is replaced by `AspectKernel::registerTransformerServices(AspectContainer $container)`, which registers deferred container definitions: override it to replace, omit, reorder or extend the built-in transformers, or simply `addLazyService()` an extra `SourceTransformer` service from `configureAop()` to append one. The rewrites of single syntax nodes (`new`, `include`/`require`, `__DIR__`/`__FILE__`) are `NodeRewriter` rules dispatched by one `SyntaxTreeRewriter` walk per file before weaving, instead of transformers walking the tree on their own; register a `NodeRewriter` service to add one. A `PrefilteredNodeRewriter` declares source markers, so files without them skip the rule, and the walk itself when no rule is left: with the default rules most files are not walked at all.
* [Performance] [BC BREAK] **Caching moved into the core: `CachingTransformer` removed** — the cache decision now lives directly in `SourceTransformingLoader::filter()`: a usable cache record makes the filter emit the cached file content as-is, without parsing the source, constructing a single transformer or building `StreamMetaData`; only a real miss parses and runs the transformer chain, then persists the result. This also removes the old cached-branch double work of re-parsing the cached file to rebuild the token stream, so the warm-cache fast path now applies in **debug mode** too (freshness checks preserved). `SourceTransformingLoader::transformCode()` returns the overall `TransformerResult` and `addTransformer()` is gone — register transformers as container services instead (`AspectKernel::registerTransformerServices()`).
* [Performance] [BC BREAK] **Advisor cache compiled to plain PHP** — the advisor cache is no longer a `serialize()`d blob under `{cacheDir}/_aspect/sha1(FQCN)`. Each aspect's pointcut/advisor graph is now compiled into an includable plain-PHP file returning `['version' => 1, 'advisors' => [...]]` built from nested static constructor expressions (advice leaves are pure-static-data `Interceptor::before(SomeAspect::class, 'method')` lazy facade calls, so including a cache file constructs no aspects and no advice closures), shadowing the aspect source below the cache directory: `{appDir}/src/Aspect/LoggingAspect.php` is cached as `{cacheDir}/src/Aspect/LoggingAspect.cache.php`. Loading a warm advisor cache is a single opcache-friendly `include` with no unserialization. **Cache format bump: clear your AOP cache directory when upgrading** (or re-run `cache:warmup:aop`); stale-format files are rebuilt automatically outside `PREBUILT_CACHE` mode. Aspects whose source is not below `appDir` are loaded directly and never cached.

**Removed**
* [BC BREAK] Removed `Features::PARAMETER_WIDENING` and the parameter-widening code path in the proxy generators. The feature was a PHP 7.0/7.1 compatibility aid (parameter type widening, wiki.php.net/rfc/parameter-no-type-variance) that has been a no-op concern since the PHP 7.2 baseline: generated proxies always keep the original parameter types. Remove the flag from `AspectKernel::configureAop()` options if you passed it.
* [BC BREAK] Removed DeclareError support, including the `DeclareError` attribute, `DeclareErrorInterceptor`, and `PointcutBuilder::declareError()`. Use `Before` or `Around` interceptors to emit user warnings or throw exceptions instead.
* [BC BREAK] Removed support for the "dynamic" pointcut (`dynamic(public Foo->method*(*))`), including `MagicMethodDynamicPointcut`, `DynamicInvocationMatcherInterceptor`, the `Pointcut::KIND_DYNAMIC` constant and the `$instanceOrScope`/`$arguments` parameters of `Pointcut::matches()`. Use a traditional execution pointcut for the magic methods instead, e.g. `execution(public Foo->__call(*))` or `execution(public Foo::__callStatic(*))`, and check the invoked method name from `$invocation->getArguments()[0]` inside the advice.
* [Removed] `SelfValueTransformer` and `SelfValueVisitor` — no longer needed with the trait-based engine.
* [BC BREAK] Interceptors are no longer `serialize()`-able: `AbstractInterceptor::__serialize()`/`__unserialize()` and the protected `serializeAdvice()`/`unserializeAdvice()` hooks are removed; advisor caches restore advices via first-class callables on aspect instances instead.

**Fixed**
* [Fixed] Generated proxies keep readable short `use` imports, and imports that collide with names of the original file (its imports, the class itself, names used in its body) or with each other (aspects sharing a short name) get a distinct alias automatically, e.g. `use Go\Aop\Framework\Interceptor as AopInterceptor;`. Such collisions used to make the proxy fail to compile (#668).
* [Fixed] Every cache file (woven sources, class and function proxies, metadata maps, advisor caches) is written atomically through `CacheFileWriter` (temporary file + rename, opcache invalidation), so a concurrent request can no longer include a half-written proxy (#670). Cache directories get search permissions matching the read bits of `cacheFileMode`, and an invalid `cacheFileMode` (outside `0600..0777` or without owner read/write) is rejected by the kernel.
* [Fixed] Intercepted property declarations spanning several lines (multi-line defaults, attributes on their own lines) are removed from the woven trait completely, and grouped declarations such as `public int $a, $b;` keep their non-intercepted items; line numbers are preserved (#669).
* [Fixed] Application and cache paths are mapped by their leading directory prefix only: a path repeating the application directory deeper inside (`/app/src/app/Foo.php`), or a sibling sharing its name prefix (`/var/www-old` for `/var/www`), no longer maps to a wrong cache file, and only the trailing `.php` extension receives the `OriginalTrait` suffix (#682).
* [Fixed] `cache:warmup:aop` returns `Command::FAILURE` when a file fails to weave, writes every error with its message to stderr, and stops at the first error with `--fail-fast`, so a deploy no longer ships an incomplete prebuilt cache. `CacheWarmer::warmUp()` returns the number of failed files; its error handler honours `error_reporting()` and `@`, and is restored when an exception escapes (#674).
* [Fixed] Advisor cache: an aspect without advisors is cached once instead of being rewritten on every request, `Features::PREBUILT_CACHE` never writes an advisor cache file (a missing file falls back to direct loading), and a cache payload with an invalid entry is rebuilt as a whole instead of silently dropping the entry (#680).
* [Fixed] `TransformerResult::Aborted` reverts the changes of the whole transformer chain as documented: the original source is served and recorded as untransformed (it used to emit the partially transformed source). Functional tests cover magic constants of unwoven files in debug and production mode (#679).
* [Fixed] Constructor and field access joinpoints are reentrant: like method and function invocations, they save and restore the outer call state. A Before advice on a repeated `new` sees `getThis() === null` instead of the previous object, a nested `new` of the same class returns the right object, the constructor invocation no longer keeps the created object alive, and an advice touching the same property of another object no longer clobbers the outer access (#672).
* [Fixed] Closure advices registered through `PointcutBuilder` get stable advisor ids derived from the expression, the advice kind and the closure's source location instead of a process-wide counter, so changing the order of registrations in `configureAop()` no longer makes cached proxies call the wrong advice or fail with an unknown id (#640).
* [Fixed] The weaving metadata files (`_transformation.cache`, `_include.cache`) carry a format version: a cache of another version is rebuilt, and with `Features::PREBUILT_CACHE` it fails with a hint to run `cache:warmup:aop`. Records keep the size and mtime of the woven source, so a source whose mtime moved backwards (rsync `-t`, checkout of an older revision) is woven again instead of serving the stale proxy (#685).

**Internal**
* [Internal] Tooling: PHPUnit 13 with strict flags, PHPStan 2 at level 10 over `src/` and `tests/`, php-cs-fixer with the PER-CS rule set, and `composer test`/`analyze`/`cs`/`check` scripts.

3.1.1 (April 1, 2026)
* Allow goaop/parser-reflection 3.x so the 3.x branch keeps working on PHP 8.2

3.1.0 (July 28, 2025)
* [BC BREAK] Requires PHP 8.2+; switched to php-parser 5 with native `PhpToken` tokens and `goaop/dissect` ^3.0 for the pointcut parser
* [BC BREAK] **Attributes instead of annotations** — aspects are declared with PHP attributes from the `Go\Lang\Attribute` namespace (`#[Before]`, `#[After]`, `#[Around]`, `#[AfterThrowing]`, `#[Pointcut]`, `#[DeclareParents]`); the doctrine/annotations integration, the `AnnotationAccess` interface and the `Lang\Annotation` namespace are removed, and `@execution`/`@within`/`@access` pointcuts match attributes
* [BC BREAK] New pointcut sub-system, with `ClassMemberReference` as a readonly DTO
* [BC BREAK] Internal container refactoring: `AspectContainer::addLazyService()` accepts only class-string identifiers, and lazy services are tagged automatically by the return type of their factory closure
* [BC BREAK] `FieldAccess` access-type constants are replaced by the `FieldAccessType` enum
* [BC BREAK] Source transformers return the `TransformerResult` enum instead of integer constants
* [BC BREAK] Framework core refactored for PHP 8.2 features and PHPStan level 9; removed the deprecated `Serializable` implementations
* [Feature] Union and intersection types in woven `self` references, `never` return type support
* Fixed: nullable return types, classes without a namespace, `AbstractMethodInvocation::$instance` access for recursive calls

3.0.0 (December 4, 2019)
* [BC BREAK] Switched to the PHP7.2 and upper, strict types, return type hints and new syntax
* [BC BREAK] Removed the Joinpoint->getThis() method, as not all joinpoints belongs to classes (eg. FunctionInvocation)
* [BC BREAK] Removed the Joinpoint->getStaticPart() method as it can return anything, better to use explicit methods 
* [Feature] Introduced the new ClassJoinpoint interface with getScope(), getThis() and isDynamic() methods
* [Feature] Implemented parameter widening feature for generated code #380
* [Feature] AnnotatedReflectionProperty provides simple access to property annotations #388 by @TheCelavi
* [Feature] Switched to the `zendframework/zend-code` package to generate code for proxies
* [Feature] Add private properties interception #412

2.0.0 (May 14, 2016)
* Dropped support for PHP<5.6, clean all old code
* [BC BREAK] Removed ability to rebind closures, because of PHP restrictions, see #247
* [BC BREAK] Removed getDefaultFeatures() method from the AspectKernel, no need in it since PHP5.6
* Migrated from the `Andrewswille/Token-Reflection` to the `goaop/parser-reflection` library for PHP5.6 and PHP7.0 support
* Added support for PHP5.6 and 7.0 features: variadic methods, scalar type hints, return type hints
* [Feature] Command-line tools for debugging aspects and advisors

1.0.0 (Feb 13, 2016)
* Dropped support for PHP<5.5, clean all old code
* Tagged public methods and interfaces with @api tag. No more changes for them in future.
* Refactored core code to use general interceptors for everything instead of separate classes
* New static initialization pointcut to intercept the moment of class loading
* New feature to intercept object initializations, requires INTERCEPT_INITIALIZATIONS to be enabled
* [BC BREAK] remove class() pointcut from the grammar #189
* [BC BREAK] make within() and @within() match all joinpoints #189
* [BC BREAK] drop @annotation syntax. Add @execution pointcut
* Pointcuts can be build now directly from closures via `PointcutBuilder` class
* Do not create files in the cache, if no aspects were applied to them, respects `includePath` option now
* `FilterInjector` is now disabled by default, this job for composer integration now
* Automatic opcache invalidation for cache state file

0.6.1 (Jul 5, 2015)
* Minor patch to fix a bug with overwriting files

0.6.0 (Feb 1, 2015)
* Interceptor for magic methods via "dynamic" pointcut. This feature also gives an access for dynamic pointcuts with different checks and conditions.
* PSR-4 standard for the codebase, thanks to @cordoval
* Added a support for splat (...) operator for more efficient advice invocation (requires PHP5.6)
* New feature system. All tunings of kernel are configured with feature-set. This breaks old configuration option `interceptFunctions=>true` use `'features' => $defaultFeatures | Features::INTERCEPT_FUNCTIONS` now
* Proxy can generate more effective invocation call with `static::class` for PHP>=5.5
* Bug-fixes with empty cache path and PSR4 code, thanks to @andy-shea
* Make pointcut grammar class compatible with PHP7.0

0.5.0 (May 24, 2014)
* Proxies are now stored in the separate files to allow more transparent debugging
* Cache warmer command added
* Extended pointcut syntax for or-ed methods: ClassName->method1|method2(*)
* Access to the annotations for method from MethodInvocation
* Support for read-only file systems (phar, GAE, etc)
* Direct access to advisors (no more serialize/unserialize)
* New @within pointcut to match classes by annotation class
* Nice demo GUI
* Deprecate the usage of submodules for framework
* Inheritance support during class-loading and weaving
* List of small fixes and imrovements

0.4.1 (Aug 27, 2013)
* Better parsing of complex "include" expressions for Yii (by @zvirusz)
* Support for dynamic arguments count for methods by checking for func_get_args() inside method body
* Fixed a bug with autoloaders reodering (by @zvirusz)

0.4.0 (Aug 04, 2013)
* Privileged advices for aspect: allows to access private and protected properties and methods of objects inside advice
* Full integration with composer that allows for easy configuration and workflow with AOP
* Fix some bugs with caching on Windows
* "True" pointcut references that gives the ability to compose a complex pointcut from a simple pointcuts.
* Pointcut now accept "$this" in references to point to the current aspect instance
  (Allows for abstract aspects and abstract pointcuts)
* AspectContainer interface was extracted. This gives the way to integrate with another DIC. Look at Warlock framework.
* Intercepting system functions such as `fopen()`, `file_get_contents()`, etc
* Annotation property pointcut was added
* Ability to declare multiple interfaces and/or traits with single `DeclareParent` introduction
* DeclareError interceptor was added. This can be used for generating an runtime error for methods that should not be executed
  in such a way.

0.3.0 (May 27, 2013)
* Support for dynamic pointcuts: pointcut that match a specific point in the code, if it is under the control
 flow (look at AspectJ cflow and cflowbelow)
* Performance optimizations
* Case-sensitive matching for pointcuts
* Primitive pointcuts (&&, ||, !)
* [BC break] Changes in the kernel configuration (look at the demo for appLoader and autoloadPaths)
* Fix a logic bug for a composite pointcuts

0.2.0 (Mar 15, 2013)
* Intercepting methods in traits
* Pointcut parser/grammar
* Huge pointcuts refactoring, cleaning
* Lazy loading services, pointcuts

0.1.1 (Jan 20, 2013)
* Introduction advice support
* Fix bug with composer autoloader prepending
* Fix doctrine/common dependency: >=2.0.0, <2.4.0

0.1.0 (Jan 08, 2013)
* Initial release of library
