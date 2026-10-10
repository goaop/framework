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

namespace Go\Instrument\ZEngine;

use Closure;
use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Exception\UnsupportedJoinpointException;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectLoaderInterface;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\DonorClassGenerator;
use ReflectionClass;
use Throwable;

/**
 * Weaves a loaded class through the engine: matches advices, generates (or reuses) the donor
 * class holding the dispatcher bodies and hands both to the method-table rewriter
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
 * @internal Framework service, not a public extension point
 */
final class ZEngineClassWeaver
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
     * Includes a donor file in an isolated scope
     *
     * @var (Closure(string): void)|null
     */
    private static ?Closure $includeFile = null;

    public function __construct(
        private readonly AspectContainer $container,
        private readonly AdviceMatcherInterface $adviceMatcher,
        private readonly AspectLoaderInterface $aspectLoader,
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
        if (isset($this->failures[$className])) {
            throw $this->failures[$className];
        }
        if (isset($this->results[$className])) {
            return $this->weave($className);
        }
        try {
            return $this->remember($className, $this->doWeave($className, $file));
        } catch (Throwable $failure) {
            $this->failures[$className] = $failure;
            throw $failure;
        }
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
            return WeaveResult::skipped('interfaces, traits and enums are not supported by the zengine driver');
        }

        $record = $this->donorCache->findFresh($className, $file, $this->container);
        if ($record !== null) {
            if ($record['donor'] === null || $record['donorClass'] === null) {
                return WeaveResult::noAdvices();
            }

            return $this->apply($className, $record['donor'], $record['donorClass'], $record['methods']);
        }

        // Cache miss: match the advices the way the stream driver does, from the parsed source
        $unloadedAspects = $this->aspectLoader->getUnloadedAspects();
        foreach ($unloadedAspects as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }
        $advisors = $this->container->getServicesByInterface(Advisor::class);

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

        $generator  = new DonorClassGenerator($class, $sortedAdvices, $imports, ClassProxyGenerator::indexMethods($class));
        $donorCode  = '<?php' . PHP_EOL . ($isStrict ? 'declare(strict_types=1);' . PHP_EOL : '') . $generator->generate();
        $donorFile  = $this->donorCache->donorFileFor($file, $class->getShortName());
        $donorClass = $generator->getDonorClassName();

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
        if (!class_exists($donorClass, false)) {
            (self::$includeFile ??= static function (string $file): void {
                include $file;
            })($donorFile);
        }
        if (!class_exists($donorClass, false)) {
            throw new WeavingException(sprintf(
                'The donor file %s of %s does not declare the donor class %s: clear the cache directory',
                $donorFile,
                $className,
                $donorClass,
            ));
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
