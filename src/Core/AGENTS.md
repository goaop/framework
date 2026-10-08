# src/Core — Container and aspect loading

## Container (Container.php)
- Generic, AOP-unaware DI container: add(by class-string|key), getService(), getValue(), addLazyService(Closure), onRegistration(interfaceFQCN, listener), addResource()
- Automatic tagging by interface; deferred services materialize as native lazy proxies via NativeLazyProxy (engine probe, no userland compatibility predicate)
- add() runs on every request: tags via class_implements(), the class file of an added object becomes a resource only on the next isFreshSince() (no reflection or stat calls at registration)
- onRegistration() never autoloads: ids of loaded classes are matched exactly, ids not loaded yet reach the listener as candidates
- Aspects are typical services — no registerAspect(); the kernel arms a debug-only Aspect::class onRegistration listener for resource tracking (files resolved via AopComposerLoader::findOriginalFile(), aspects stay unloaded) and registers framework services via FrameworkServices::register(); CachePathManager is added eagerly by the kernel (the class loader reads its cache index right away)
- Throws SPL exceptions only (InvalidArgumentException, UnexpectedValueException, OutOfBoundsException)

## Aspect loading
- AspectLoader — scans aspect classes for pointcut/advice attributes → Advisor[]; CachedAspectLoader decorates it (both implement AspectLoaderInterface)

### Compiled advisor cache
- Lives in the nested Go\Core\Cache namespace (CachedAspectLoader, AdvisorCacheCompiler, AdvisorCachePrinter, CacheFileWriter — all @internal); format, naming and error-handling rules: see src/Core/Cache/AGENTS.md
- AttributeAspectLoaderExtension — handles PHP 8 attribute-based aspect definitions; throws AspectException for non-public advice methods (first-class callable advices require public visibility; #[Pointcut]-only methods exempt)
- AdviceMatcher — given class reflector, returns applicable advisors keyed by join point
  - Scans IS_PUBLIC|IS_PROTECTED|IS_PRIVATE methods
  - Private methods from parent classes excluded

## Bridge
src/Bridge/Doctrine (ORM 3.6+, persistence 4.1+ ClassLocator; no metadata surgery needed under the trait engine):
- WovenEntityClassLocator — ClassLocator for mapping drivers: parses class names (parser-reflection ReflectionFile, never includes files) and loads them via class_exists() so the autoloader serves the woven class. Doctrine's FileClassLocator require_once's sources: bypasses weaving / misses already-loaded woven entities.
- MetadataLoadInterceptor — loadClassMetadata guard: woven entity (implements Go\Aop\Proxy) with a hooked (intercepted) mapped property while native lazy objects are off → RuntimeException pointing to enableNativeLazyObjects(true).
- Functional coverage: tests/Functional/DoctrineBridgeTest.php (fixture script tests/Fixtures/project/bin/doctrine-metadata.php, no DB connection needed).
