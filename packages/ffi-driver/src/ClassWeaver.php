<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Driver\Ffi;

use Closure;
use Go\Aop\Aspect;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Aop\Pointcut;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AdvisorIndex;
use Go\Core\AspectContainer;
use Go\Driver\Ffi\Exception\FfiWeavingException;
use Go\Driver\Ffi\Exception\UnsupportedJoinpointException;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\FunctionProxyGenerator;
use ReflectionClass;
use Throwable;

/**
 * Weaves a loaded class through the engine: matches advices, generates (or reuses) the donor
 * trait holding the dispatcher bodies and hands both to the method-table rewriter
 *
 * Per request every class is woven at most once; a failure is remembered so that a later call
 * for the same class rethrows the same exception instead of reporting it as unadvised (the class
 * is declared and linked by then, but NOT woven).
 *
 * Donor cache miss: the file of the loaded class is parsed with goaop/parser-reflection, so the
 * advice matching and the donor generation see what the stream driver sees (attributes, parameter
 * defaults, `self`/`parent` types and the file's imports verbatim); native reflection is the
 * fallback for a class whose file cannot be parsed. A warm donor record skips both.
 *
 * Function interception (a registered `execution(Ns\func(*))` pointcut) needs no engine mutation at all: the
 * stream driver's function proxies - namespaced functions shadowing the advised internal ones,
 * which PHP resolves an unqualified call to before the global function - are generated per
 * namespace and included right after the file declaring a class of that namespace, before any
 * of its code runs ({@see weaveFunctions()}).
 *
 * @internal Driver service, not a public extension point
 */
final class ClassWeaver
{
    /**
     * Join-point kinds the runtime rewiring supports: method bodies only
     */
    private const array SUPPORTED_KINDS = [AspectContainer::METHOD_PREFIX, AspectContainer::STATIC_METHOD_PREFIX];

    /**
     * Results per class name of this request
     *
     * @var array<string, WeaveResult>
     */
    private array $results = [];

    /**
     * Failures per class name of this request, rethrown on a later call
     *
     * @var array<string, Throwable>
     */
    private array $failures = [];

    /**
     * Function-proxy files declared in this request, keyed by namespace name (null: nothing proxied there)
     *
     * @var array<string, string|null>
     */
    private array $functionNamespaces = [];

    /**
     * Includes a donor or function-proxy file in an isolated scope
     *
     * @var (Closure(string): void)|null
     */
    private static ?Closure $includeFile = null;

    public function __construct(
        private readonly AspectContainer $container,
        private readonly AdviceMatcherInterface $adviceMatcher,
        private readonly AdvisorIndex $advisorIndex,
        private readonly CachePathManager $cachePathManager,
        private readonly DonorCacheIndex $donorCache,
        private readonly MethodTableRewriter $rewriter,
    ) {}

    /**
     * Weaves a class that is already loaded (the public entry point for classes not loaded
     * through composer: required files, classes declared before the kernel was initialized)
     *
     * Weave before instances exist and before subclasses link: the engine mutation reaches
     * neither objects created earlier (under opcache) nor, for a shared-memory class, subclasses
     * linked against the shared entry.
     *
     * @throws UnsupportedJoinpointException When an advice matched a join point kind this driver cannot weave
     */
    public function weave(string $className): WeaveResult
    {
        $className = self::declaredName($className);
        if (isset($this->failures[$className])) {
            throw $this->failures[$className];
        }
        if (isset($this->results[$className])) {
            $previous = $this->results[$className];

            return $previous->outcome === WeaveOutcome::Woven ? WeaveResult::alreadyWoven($previous->methods) : $previous;
        }
        if (!class_exists($className, false)) {
            return WeaveResult::skipped('the class is not loaded');
        }
        $file = (new ReflectionClass($className))->getFileName();
        if ($file === false) {
            return $this->remember($className, WeaveResult::skipped('the class has no source file (internal or runtime-generated)'));
        }

        return $this->weaveLoadedClass($className, $file);
    }

    /**
     * Weaves the class the loader just included from $file
     *
     * @throws UnsupportedJoinpointException When an advice matched a join point kind this driver cannot weave
     */
    public function weaveLoadedClass(string $className, string $file): WeaveResult
    {
        $className = self::declaredName($className);
        if (isset($this->failures[$className])) {
            throw $this->failures[$className];
        }
        if (isset($this->results[$className])) {
            return $this->weave($className);
        }
        try {
            // The function proxies of the namespace come first: a class of it is loaded, so code of
            // that namespace is about to run
            $this->weaveFunctions(self::namespaceOf($className), $file);

            return $this->remember($className, $this->doWeave($className, $file));
        } catch (Throwable $failure) {
            $this->failures[$className] = $failure;
            throw $failure;
        }
    }

    /**
     * Declares the function proxies of a namespace, once per request
     *
     * While a function pointcut is registered (AdvisorIndex), an advised internal function called
     * unqualified from a namespace is shadowed by a namespaced proxy function of the same name, generated the way the
     * stream driver generates them (the proxy dispatches to the advices and reaches the original
     * through its fully-qualified name). PHP resolves an unqualified call to the namespaced function
     * first, so the proxies only have to be declared before the calling code runs - which is what
     * including them right after the file declaring a class of the namespace guarantees. A call site
     * that already ran keeps the global function it resolved (PHP caches the resolution per call
     * site), like a class loaded before the kernel keeps its original methods. The global namespace
     * cannot be served: a proxy there would redeclare the internal function.
     *
     * The proxy file is generated below `{cacheDir}/_ffi/_functions/` on the first request and
     * recorded in the donor index with the function names; a warm request includes it without
     * loading the aspects. The functions of a namespace already declared in this process (another
     * kernel of a test runner) are never included again.
     *
     * @param string $namespace Namespace to declare the proxies for
     * @param string $file      A source file declaring that namespace block, parsed for the matching
     *
     * @return string|null The proxy file declared for the namespace, null when nothing is proxied there
     */
    public function weaveFunctions(string $namespace, string $file): ?string
    {
        $namespace = trim($namespace, '\\');
        if ($namespace === '') {
            return null;
        }
        if (array_key_exists($namespace, $this->functionNamespaces)) {
            return $this->functionNamespaces[$namespace];
        }
        // A file outside the application root decides nothing for its namespace
        if (!$this->donorCache->isApplicationSource($file)) {
            return null;
        }
        $this->functionNamespaces[$namespace] = null;

        // A fresh record decides without the aspects; only a miss asks the advisor index, which loads them
        $record = $this->donorCache->findFreshFunctions($namespace, $this->container);
        if ($record === null) {
            if (!$this->advisorIndex->hasPointcutKind(Pointcut::KIND_FUNCTION)) {
                if ($this->advisorIndex->getAdvisors() === []) {
                    // No advisor at all yet (a class loaded while the kernel is still being configured):
                    // nothing is decided, the question is asked again for the next class of the namespace
                    unset($this->functionNamespaces[$namespace]);

                    return null;
                }
                // No function pointcut registered: recorded, so a warm request does not ask again
                $this->donorCache->recordFunctions($namespace, null, []);

                return null;
            }
            $record = $this->generateFunctionProxies($namespace, $file);
        }
        if ($record['file'] === null) {
            return null;
        }
        foreach ($record['functions'] as $function) {
            if (function_exists($namespace . '\\' . $function)) {
                // Declared by an earlier kernel of this process: a function cannot be declared twice
                return $this->functionNamespaces[$namespace] = $record['file'];
            }
        }
        (self::$includeFile ??= static function (string $file): void {
            include $file;
        })($record['file']);

        return $this->functionNamespaces[$namespace] = $record['file'];
    }

    /**
     * Matches the function advisors against the namespace and writes its proxy file (recorded either way)
     *
     * @return array{file: string|null, functions: list<string>}
     */
    private function generateFunctionProxies(string $namespace, string $file): array
    {
        try {
            $fileNamespace = new ReflectionFileNamespace($file, $namespace);
        } catch (Throwable) {
            // The file declares no such namespace block (a class name spelled in another case than its
            // declaration, a source the parser cannot read): nothing to match against, and not worth a record
            return ['file' => null, 'functions' => []];
        }
        $advisors = $this->advisorIndex->getAdvisors();
        $advices  = $this->adviceMatcher->getAdvicesForFunctions($fileNamespace, $advisors);
        if ($advices === []) {
            $this->donorCache->recordFunctions($namespace, null, []);

            return ['file' => null, 'functions' => []];
        }
        $sortedAdvices = AbstractJoinpoint::flatAndSortAdvices($advices);
        $functions     = array_map(strval(...), array_keys($sortedAdvices[AspectContainer::FUNCTION_PREFIX] ?? []));
        $proxyFile     = $this->donorCache->functionsFileFor($namespace);
        $generator     = new FunctionProxyGenerator($fileNamespace, $sortedAdvices);

        $this->cachePathManager->getCacheFileWriter()->write($proxyFile, $generator->generate());
        $this->donorCache->recordFunctions($namespace, $proxyFile, $functions);

        return ['file' => $proxyFile, 'functions' => $functions];
    }

    /**
     * The name a loaded class was declared with: the loader hands over the name as the code spelled it, which
     * may differ in case; the results, the index records and the engine lookups all use the declared spelling
     */
    private static function declaredName(string $className): string
    {
        return class_exists($className, false) ? new ReflectionClass($className)->getName() : $className;
    }

    /**
     * Namespace of a class name, '' for the global namespace
     */
    private static function namespaceOf(string $className): string
    {
        $separator = strrpos($className, '\\');

        return $separator === false ? '' : substr($className, 0, $separator);
    }

    private function doWeave(string $className, string $file): WeaveResult
    {
        if (!class_exists($className, false)) {
            return WeaveResult::skipped('the file did not declare the class');
        }
        $native = new ReflectionClass($className);
        if ($native->isInternal()) {
            return WeaveResult::skipped('internal classes are never woven');
        }
        if ($native->isInterface() || $native->isTrait() || $native->isEnum()) {
            return WeaveResult::skipped('interfaces, traits and enums are not supported by the ffi driver');
        }
        if (!$this->donorCache->isApplicationSource($file)) {
            return WeaveResult::skipped('sources outside the application root are never woven');
        }

        $record = $this->donorCache->findFresh($className, $file, $this->container);
        if ($record !== null) {
            if ($record['donor'] === null || $record['donorClass'] === null) {
                return WeaveResult::noAdvices();
            }

            return $this->apply($className, $record['donor'], $record['donorClass'], $record['methods']);
        }

        // Cache miss: match the advices the way the stream driver does, from the parsed source
        $advisors = $this->advisorIndex->getAdvisors();

        [$class, $imports, $isStrict] = $this->reflectSource($className, $file);
        try {
            $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);
        } catch (Throwable $matchingError) {
            // Aspects used to be skipped before matching, so matching one must not fail the load
            if ($this->isAspectSafe($class)) {
                return WeaveResult::skipped('aspects are never woven');
            }
            throw $matchingError;
        }
        if ($advices === []) {
            $this->donorCache->recordNoAdvices($className, $file);

            return WeaveResult::noAdvices();
        }
        // Aspects are never woven (checked after matching: it reflects every ancestor, #748)
        if ($class->implementsInterface(Aspect::class)) {
            return WeaveResult::skipped('aspects are never woven');
        }
        $this->refuseUnsupportedKinds($className, $advices);

        $sortedAdvices = AbstractJoinpoint::flatAndSortAdvices($advices);
        $methods       = array_values(array_unique(array_merge(
            array_keys($sortedAdvices[AspectContainer::METHOD_PREFIX] ?? []),
            array_keys($sortedAdvices[AspectContainer::STATIC_METHOD_PREFIX] ?? []),
        )));
        $methods = array_map(strval(...), $methods);

        $generator  = new DonorTraitGenerator($class, $sortedAdvices, $imports, ClassProxyGenerator::indexMethods($class));
        $donorCode  = '<?php' . PHP_EOL . ($isStrict ? 'declare(strict_types=1);' . PHP_EOL : '') . $generator->generate();
        $donorFile  = $this->donorCache->donorFileFor($file, $class->getShortName());
        $donorClass = $generator->getDonorTraitName();

        $this->cachePathManager->getCacheFileWriter()->write($donorFile, $donorCode);
        $this->donorCache->recordDonor($className, $file, $donorFile, $donorClass, $methods);

        return $this->apply($className, $donorFile, $donorClass, $methods);
    }

    /**
     * Loads the donor file and rewires the advised methods
     *
     * @param class-string $className
     * @param list<string> $methods
     */
    private function apply(string $className, string $donorFile, string $donorClass, array $methods): WeaveResult
    {
        if (!trait_exists($donorClass, false)) {
            (self::$includeFile ??= static function (string $file): void {
                include $file;
            })($donorFile);
        }
        if (!trait_exists($donorClass, false)) {
            throw FfiWeavingException::donorNotDeclared($donorFile, $className, $donorClass);
        }
        $this->rewriter->rewire($className, $donorClass, $methods);

        return WeaveResult::woven($methods);
    }

    /**
     * Reflects the loaded class from its parsed source, falling back to native reflection
     *
     * @param class-string $className
     *
     * @return array{ReflectionClass<object>, array<string, string|null>, bool} Class reflection, imports of its
     *                                                                         namespace block, strict-types mode
     */
    private function reflectSource(string $className, string $file): array
    {
        try {
            $parsedFile = new ReflectionFile($file);
            foreach ($parsedFile->getFileNamespaces() as $namespace) {
                foreach ($namespace->getClasses() as $parsedClass) {
                    if (strcasecmp($parsedClass->getName(), $className) === 0) {
                        return [$parsedClass, $namespace->getNamespaceAliases(), $parsedFile->isStrictMode()];
                    }
                }
            }
        } catch (Throwable) {
            // A source the parser cannot handle: native reflection below knows the loaded class anyway
        }
        $native = new ReflectionClass($className);

        return [$native, [], self::declaresStrictTypes($file)];
    }

    /**
     * Whether the file opens with `declare(strict_types=1)`: the donor must mirror it, since the strict-types flag
     * of the dispatcher bodies follows the donor file
     */
    private static function declaresStrictTypes(string $file): bool
    {
        $source = @file_get_contents($file, false, null, 0, 4096);

        return is_string($source) && preg_match('/^<\?php\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)/i', ltrim($source)) === 1;
    }

    /**
     * Refuses advices of the join-point kinds the runtime rewiring cannot provide
     *
     * @param array<string, array<string, array<string, mixed>>> $advices Advices keyed by kind prefix, name and advisor id
     */
    private function refuseUnsupportedKinds(string $className, array $advices): void
    {
        $unsupportedKinds = [];
        $advisorIds       = [];
        foreach ($advices as $kind => $joinpoints) {
            if (in_array($kind, self::SUPPORTED_KINDS, true)) {
                continue;
            }
            $unsupportedKinds[] = $kind;
            foreach ($joinpoints as $kindAdvices) {
                foreach (array_keys($kindAdvices) as $advisorId) {
                    $advisorIds[] = $advisorId;
                }
            }
        }
        if ($unsupportedKinds !== []) {
            throw UnsupportedJoinpointException::forClass($className, $unsupportedKinds, array_values(array_unique($advisorIds)));
        }
    }

    /**
     * Checks whether the class is an aspect, a failure of the check counts as "not an aspect"
     *
     * @param ReflectionClass<object> $class
     */
    private function isAspectSafe(ReflectionClass $class): bool
    {
        try {
            return $class->implementsInterface(Aspect::class);
        } catch (Throwable) {
            return false;
        }
    }

    private function remember(string $className, WeaveResult $result): WeaveResult
    {
        $this->results[$className] = $result;

        return $result;
    }
}
