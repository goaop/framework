# src/Core — Container and aspect loading

## Container (Container.php)
- DI container: add(by class-string|key), getService(), addLazyService(Closure)
- Automatic tagging by interface

## Aspect loading
- AspectLoader / CachedAspectLoader — scan aspect classes for pointcut/advice attributes → Advisor[]
- AttributeAspectLoaderExtension — handles PHP 8 attribute-based aspect definitions
- AdviceMatcher — given class reflector, returns applicable advisors keyed by join point
  - Scans IS_PUBLIC|IS_PROTECTED|IS_PRIVATE methods
  - Private methods from parent classes excluded

## Bridge
src/Bridge/Doctrine/MetadataLoadInterceptor.php — workaround for Doctrine ORM entity weaving (Doctrine loads metadata before kernel can intercept classes).
