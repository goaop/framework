# src/Core — Container and aspect loading

## Container (Container.php)
- DI container: add(by class-string|key), getService(), addLazyService(Closure)
- Automatic tagging by interface

## Aspect loading
- AspectLoader — scans aspect classes for pointcut/advice attributes → Advisor[]; CachedAspectLoader decorates it (both implement AspectLoaderInterface)

### Compiled advisor cache
- Lives in the nested Go\Core\Cache namespace (CachedAspectLoader, AdvisorCacheCompiler, AdvisorCachePrinter, CacheFileWriter, NotCompilableException — all @internal); format, naming and error-handling rules: see src/Core/Cache/AGENTS.md
- AttributeAspectLoaderExtension — handles PHP 8 attribute-based aspect definitions; throws AspectException for non-public advice methods (first-class callable advices require public visibility; #[Pointcut]-only methods exempt)
- AdviceMatcher — given class reflector, returns applicable advisors keyed by join point
  - Scans IS_PUBLIC|IS_PROTECTED|IS_PRIVATE methods
  - Private methods from parent classes excluded

## Bridge
src/Bridge/Doctrine/MetadataLoadInterceptor.php — workaround for Doctrine ORM entity weaving (Doctrine loads metadata before kernel can intercept classes).
