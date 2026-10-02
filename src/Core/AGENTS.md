# src/Core — Container and aspect loading

## Container (Container.php)
- Generic, AOP-unaware DI container: add(by class-string|key), getService(), getValue(), addLazyService(Closure), onRegistration(interfaceFQCN, listener), addResource()
- Automatic tagging by interface; deferred services materialize as native lazy proxies via NativeLazyProxy (engine probe, no userland compatibility predicate)
- Aspects are typical services — no registerAspect(); the kernel arms a debug-only Aspect::class onRegistration listener for resource tracking and registers framework services via FrameworkServices::register()
- Throws SPL exceptions only (InvalidArgumentException, UnexpectedValueException, OutOfBoundsException)

## Aspect loading
- AspectLoader — scans aspect classes for pointcut/advice attributes → Advisor[]; CachedAspectLoader decorates it (both implement AspectLoaderInterface)

### Compiled advisor cache
- Lives in the nested Go\Core\Cache namespace (CachedAspectLoader, AdvisorCacheCompiler, AdvisorCachePrinter, CacheFileWriter, NotCompilableException — all @internal); format, naming and error-handling rules: see src/Core/Cache/AGENTS.md
- AttributeAspectLoaderExtension — handles PHP 8 attribute-based aspect definitions; throws AspectException for non-public advice methods (first-class callable advices require public visibility; #[Pointcut]-only methods exempt)
- AdviceMatcher — given class reflector, returns applicable advisors keyed by join point
  - Scans IS_PUBLIC|IS_PROTECTED|IS_PRIVATE methods
  - Private methods from parent classes excluded

## Bridge
src/Bridge/Doctrine (ORM 3.6+, persistence 4.1+ ClassLocator; no metadata surgery needed under the trait engine):
- WovenEntityClassLocator — ClassLocator for mapping drivers: parses class names (parser-reflection ReflectionFile, never includes files) and loads them via class_exists() so the autoloader serves the woven class. Doctrine's FileClassLocator require_once's sources: bypasses weaving / misses already-loaded woven entities.
- MetadataLoadInterceptor — loadClassMetadata guard: woven entity (implements Go\Aop\Proxy) with a hooked (intercepted) mapped property while native lazy objects are off → RuntimeException pointing to enableNativeLazyObjects(true).
- Functional coverage: tests/Functional/DoctrineBridgeTest.php (fixture script tests/Fixtures/project/bin/doctrine-metadata.php, no DB connection needed).
