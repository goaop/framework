# packages/ffi-driver — goaop/ffi-driver (Go\Driver\Ffi\)

Weaving driver of Go! AOP: runtime method-table weaving through lisachenko/z-engine (ext-ffi), `'driver' => FfiDriver::class`.
Separate composer package (own composer.json: php ~8.4.0 || ~8.5.0, ext-ffi, goaop/framework ^4.0, lisachenko/z-engine ~8.4.3 || ~8.5.1),
mapped by the root autoload-dev (`Go\Driver\Ffi\` → src/ and tests/). Docs: docs/driver/ffi.md.

## Commands (repo root)
- `composer require --dev lisachenko/z-engine` — packagist resolves the release of the running PHP minor; never a vcs repo, never a branch, never in the root composer.json
- `composer test:ffi` — `php -d ffi.enable=1 -d opcache.jit=off -d opcache.jit_buffer_size=0 vendor/bin/phpunit --testsuite ffi-driver`; excluded from `composer test`; self-skips without the engine
- `composer analyze:ffi` — phpstan with packages/ffi-driver/phpstan.neon (level 10, needs z-engine installed; baseline packages/ffi-driver/phpstan-baseline.php; tests/Stubs excluded)
- CI: .github/workflows/ffi-driver.yml only (PHP 8.4 + 8.5, installs z-engine, analyze:ffi, test:ffi, then `composer test` with the engine present)

## Boot (FfiDriver::boot(), WeavingDriver, NOT a SourceTransformingDriver → no transformer chain)
1. refuses Features::INTERCEPT_INCLUDES (EngineConfigurationException::includeInterceptionNotSupported)
2. JitGuard::enforce() BEFORE anything of z-engine is autoloaded (z-engine's classes must compile with the JIT off) (z-engine's classes must compile with the JIT off): detection via `opcache_get_status()['jit']['on']` (ini value unusable); active → `ini_set('opcache.jit', 'off')` (never 'disable', it locks the directive), still on → jitStillOn; report {jitWasActive, previousMode, disabledAtRuntime}. Process-level `opcache.jit=off` + `opcache.jit_buffer_size=0` stays the documented requirement (code compiled before the kernel keeps its JIT state)
3. requires z-engine (loaded, or known to composer: InstalledVersions) → engineNotInstalled; requires the method-table API (redefine() with preserveAs, addMethod(), fromClassTable(): releases 8.4.3 / 8.5.1) → engineWithoutMethodTableApi
4. Core::init() unless initialized (failure → engineBootFailed)
5. boot report in the container under `kernel.ffi.boot` (FfiDriver::BOOT_REPORT; getBootReport($container)): driver, php, opcache, jit
6. installed z-engine package joins excludePaths; lazy services DonorCacheIndex, MethodTableRewriter, ClassWeaver
7. FfiComposerLoader::init(): wraps every composer loader in spl_autoload_functions() (unwrapping any ComposerLoaderDecorator), loadClass() includes the original file natively (opcache, magic constants, line numbers untouched) then ClassWeaver::weaveLoadedClass() when the Enumerator path filter allows — the subclass that triggered the autoload links against the already woven parent

## ClassWeaver (@internal)
- once per class per request; failures remembered and rethrown on a later call (class declared + linked but NOT woven); `weave($class)` is the public entry for classes declared before the kernel (require'd directly), weave before instances exist and before subclasses link
- weaveLoadedClass(): weaveFunctions(namespace, file) first, then doWeave(): skip internal/interface/trait/enum/outside appDir; DonorCacheIndex::findFresh() → warm: include donor + rewire, no aspects loaded; miss: ReflectionFile parse (native fallback), AdviceMatcher::getAdvicesForClass(), aspects never woven (checked after matching, #748, matcher failure on an aspect swallowed), refuseUnsupportedKinds() (only METHOD_PREFIX/STATIC_METHOD_PREFIX → UnsupportedJoinpointException::forClass), DonorTraitGenerator → write donor + recordDonor()
- functions: no flag, `AdvisorIndex::hasPointcutKind(Pointcut::KIND_FUNCTION)`; fresh record decides first (a "none" record is written when no function pointcut exists, so warm requests never load aspects); FunctionProxyGenerator output → `{cacheDir}/_ffi/_functions/<Ns/Path>.php`, recorded per namespace (file + names, fresh by tracked resources only), included right after the file declaring a class of the namespace; never twice per process (function_exists guard), never for the global namespace, unqualified calls only, call sites that already ran keep the global function

## DonorTraitGenerator (extends ClassProxyGenerator)
- donor = TRAIT `Ns\<Short>OriginalTrait` (AspectContainer::ORIGINAL_TRAIT_SUFFIX, same name as the stream driver's woven trait, but never used by a class: the engine adopts the bodies); same namespace + original imports (ProxyImports), declares only the advised methods with the proxy dispatcher bodies (`$this->mOriginalAlias(...)` / `self::mOriginalAlias(...)` own methods, `parent::m(...)` inherited)
- in a trait `self::class`/`parent::` bind at run time to the woven class → only `self`/`parent` KEYWORDS OF SIGNATURES are spelled out (TypeGenerator::resolveScopeKeywords(), ParameterGenerator::withType(), MethodGenerator::getParameters()/setParameters(); `static` kept) because the engine compares the donor signature with the swapped entry
- file: `{cacheDir}/_ffi/<path relative to appDir>/<Short>OriginalTrait.php`, `declare(strict_types=1)` mirrored from the source (dispatcher strictness follows the donor file)
- non-method kinds in the constructor → FfiWeavingException::unsupportedDonorKinds

## MethodTableRewriter (@internal, z-engine public reflection API only)
- own method: `$class->getMethod($m)->redefine($donorMethod, preserveAs: $m . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX)` ('<m>OriginalAlias', published entry pointer / magic slots / subclass buckets / inline caches stay valid)
- inherited method: `$class->addMethod($m, $donorMethod)` (override whose dispatcher reaches `parent::m()`)
- `EngineClass::fromClassTable()` re-resolved per method (first mutation copies an opcache-shared class out of SHM, repoints the bucket); afterwards `addInterfaces(Go\Aop\Proxy)` (instanceof Proxy answers like a generated proxy)
- ReflectionException from the engine → UnsupportedJoinpointException::engineRefusal (class partially woven: methods rewired before keep dispatching)

## DonorCacheIndex (@internal)
- `{cacheDir}/_ffi.cache` (FORMAT_VERSION 2: version, classes{source, filemtime, filesize, cachedAt, donor, donorClass, methods}, functions{file, functions, cachedAt}); freshness = CachePathManager::isFreshRecord() (the one shared rule; functions: container isFreshSince only); written via CachePathManager::writeIndexFile(), flushed at destruct (exceptions swallowed)
- separate from _transformation.cache on purpose: a record without cacheUri means "serve natively" to the stream filter driver
- donorFileFor()/functionsFileFor() need cacheDir (InvalidConfigurationException::cacheIndexWithoutCacheDir); isApplicationSource() = below appDir

## Exceptions (src/Exception/)
- EngineConfigurationException extends Go\Aop\Exception\InvalidConfigurationException: engineNotInstalled, engineWithoutMethodTableApi, engineBootFailed, jitStillOn, includeInterceptionNotSupported
- FfiWeavingException extends Go\Aop\Exception\WeavingException: unsupportedDonorKinds, sourceOutsideApplicationRoot, donorNotDeclared
- UnsupportedJoinpointException extends FfiWeavingException: forClass, classNotInEngineClassTable, engineRefusal

## Not supported (source-transforming drivers only)
property access, introductions, initialization/staticinitialization, Features::INTERCEPT_INCLUDES, traits/enums/interfaces as targets, CacheWarmer / cache:warmup:aop / debug:weaving (refused by the framework: no SourceTransformingDriver). Known deviations: reflection of an advised method reports the donor file; a single-literal private/final body may be inlined by the optimizer before the swap; a call site run before the function proxies were declared keeps the global function.

## Tests (tests/, suite `ffi-driver`, `#[Group('ffi')]`)
- unit: DonorCacheIndexTest, DonorTraitGeneratorTest, FfiComposerLoaderTest, FfiDriverTest (closure seams of the constructor), JitGuardTest, Exception/
- InProcess/ (InProcessTestCase): boots z-engine INSIDE the runner, kernel created without the singleton (installed for the test, restored after), autoloaders restored, weaves tests/Stubs for real — one stub class per test (a woven class stays woven); the only coverage of the driver
- Functional/ (FfiFunctionalTestCase): subprocesses of tests/Fixtures/project/bin/driver-weaving.php (`GO_AOP_CONFIGURATION` = `ffi`, `ffi_production`, `ffi_unsupported`; `ffi_reference` = same fixtures through StreamFilterDriver, reports compared; both opcache legs; `ZENGINE_AUTOBOOT=0`); fixtures Application/Ffi*, Aspect/FfiAspect, Kernel/FfiAspectKernel + FfiUnsupportedAspectKernel
- tests/Stubs: weaving inputs (excluded from phpstan)
