<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoaderInterface;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\Transformer\Weaving\InterceptedMembers;
use Go\Instrument\Transformer\Weaving\ProxyCache;
use Go\Instrument\Transformer\Weaving\TokenStreamEditor;
use Go\Instrument\Transformer\Weaving\TraitConverter;
use Go\Instrument\Transformer\Weaving\WovenClass;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\EnumProxyGenerator;
use Go\Proxy\FunctionProxyGenerator;
use Go\Proxy\TraitProxyGenerator;
use Throwable;

/**
 * Main transformer that performs weaving of aspects into the source code
 *
 * Orchestrates the weaving of one file: matches the advisors against every class-like and namespace, converts each
 * advised class-like into a trait ({@see TraitConverter}), generates its proxy and saves the proxies into the cache
 * ({@see ProxyCache}). The woven file includes every proxy right after the declaration it replaces.
 */
final class WeavingTransformer extends BaseSourceTransformer
{
    /**
     * Writes the generated proxies into the cache directory
     */
    private readonly ProxyCache $proxyCache;

    public function __construct(
        AspectKernel $kernel,
        /** Advice matcher for class */
        private readonly AdviceMatcherInterface $adviceMatcher,
        /** Cache manager */
        private readonly CachePathManager $cachePathManager,
        /** Loader for aspects */
        private readonly AspectLoaderInterface $aspectLoader,
        /** Converts the source of an advised class-like into the trait its proxy uses */
        private readonly TraitConverter $traitConverter = new TraitConverter(),
    ) {
        parent::__construct($kernel);
        $this->proxyCache = new ProxyCache($cachePathManager, $this->container, $this->options['appDir']);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     */
    public function transform(StreamMetaData $metadata): TransformerResult
    {
        $parsedSource = new ReflectionFile($metadata->uri, $metadata->syntaxTree);
        $editor       = new TokenStreamEditor($metadata);

        // Check if we have some new aspects that weren't loaded yet
        foreach ($this->aspectLoader->getUnloadedAspects() as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }
        $advisors = $this->container->getServicesByInterface(Advisor::class);

        $totalTransformations = 0;
        $wovenClasses         = [];
        foreach ($parsedSource->getFileNamespaces() as $namespace) {
            foreach ($namespace->getClasses() as $class) {
                // Every discovered class (woven or not) is recorded so the runtime class
                // map / skip set can be built for the autoloader at flush time
                $this->cachePathManager->registerClassForResource($metadata->uri, $class->getName());

                // Skip interfaces — enums are supported via EnumProxyGenerator. Aspects are skipped
                // in weaveClass() once advices were found: the check reflects every ancestor
                if ($class->isInterface()) {
                    continue;
                }
                $wovenClass = $this->weaveClass($advisors, $editor, $class, $namespace, $parsedSource->isStrictMode());
                if ($wovenClass !== null) {
                    $wovenClasses[] = $wovenClass;
                }
            }
            $totalTransformations += (int) $this->weaveFunctions($advisors, $editor, $namespace);
        }

        // Proxies are saved once the whole file is woven: their file names depend on the number of woven classes
        foreach ($wovenClasses as $wovenClass) {
            $includeProxy = $this->proxyCache->saveClassProxy(
                $wovenClass->class,
                $wovenClass->proxyCode,
                count($wovenClasses) === 1,
            );
            $editor->append($wovenClass->lastTokenPosition, PHP_EOL . $includeProxy);
        }
        $totalTransformations += count($wovenClasses);

        return ($totalTransformations > 0) ? TransformerResult::Transformed : TransformerResult::Abstain;
    }

    /**
     * Converts an advised class-like into a trait and generates its proxy, returns null if the class was not woven
     *
     * @param Advisor[]               $advisors      List of advisors
     * @param ReflectionFileNamespace $namespace     Namespace block of the file that declares the class
     * @param bool                    $useStrictMode If the source file used strict mode, the proxy should too
     */
    private function weaveClass(
        array $advisors,
        TokenStreamEditor $editor,
        ReflectionClass $class,
        ReflectionFileNamespace $namespace,
        bool $useStrictMode,
    ): ?WovenClass {
        try {
            $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);
        } catch (Throwable $matchingError) {
            // Aspects used to be skipped before matching, so matching one must not fail the file
            if ($this->isAspectSafe($class)) {
                return null;
            }
            throw $matchingError;
        }

        if ($advices === []) {
            // Fast return if there aren't any advices for that class. It comes before the aspect check,
            // as that one reflects (and parses) every ancestor and fails when one can't be located
            return null;
        }

        // Aspects are never woven
        if ($class->implementsInterface(Aspect::class)) {
            return null;
        }

        // Sort advices in advance to keep the correct order in cache, and leave only keys for the cache
        $advices = AbstractJoinpoint::flatAndSortAdvices($advices);

        $traitName = $class->getShortName() . AspectContainer::ORIGINAL_TRAIT_SUFFIX;
        $traitFqcn = ($class->getNamespaceName() !== '' ? $class->getNamespaceName() . '\\' : '') . $traitName;

        // Imports of the namespace block declaring the class are copied into the proxy (parameter defaults
        // and types rely on them); the generators reserve their names and alias their own imports around them.
        // They are read from the block being woven: a lookup by file and namespace name returns the first block
        // when the file declares the same namespace several times
        $originalImports = $namespace->getNamespaceAliases();
        // Intercepted methods are looked up by name several times per method, here and in the proxy generators
        $methods = ClassProxyGenerator::indexMethods($class);

        $this->traitConverter->convert($class, InterceptedMembers::fromAdvices($advices), $methods, $editor, $traitName);

        $proxyGenerator = match (true) {
            $class->isTrait() => new TraitProxyGenerator($class, $traitFqcn, $advices, $originalImports, $methods),
            $class->isEnum()  => new EnumProxyGenerator($class, $traitFqcn, $advices, $originalImports, $methods),
            default           => new ClassProxyGenerator($class, $traitFqcn, $advices, $originalImports, $methods),
        };
        $proxyCode = $proxyGenerator->generate();
        if ($useStrictMode) {
            $proxyCode = 'declare(strict_types=1);' . PHP_EOL . $proxyCode;
        }

        [, $lastTokenPosition] = TokenStreamEditor::declarationRange($class->getNode());

        return new WovenClass($class, $proxyCode, $lastTokenPosition);
    }

    /**
     * Checks whether the class is an aspect, a failure of the check counts as "not an aspect"
     */
    private function isAspectSafe(ReflectionClass $class): bool
    {
        try {
            return $class->implementsInterface(Aspect::class);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Performs weaving of functions in the namespace, returns true if functions were woven
     *
     * @param Advisor[] $advisors List of advisors
     */
    private function weaveFunctions(array $advisors, TokenStreamEditor $editor, ReflectionFileNamespace $namespace): bool
    {
        // A function proxy shadows the internal function with a namespaced one of the same name: that is
        // impossible in the global namespace, where it would redeclare the internal function
        if ($namespace->getName() === '') {
            return false;
        }
        $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        if ($functionAdvices === []) {
            return false;
        }
        $includeProxy = $this->proxyCache->saveFunctionProxy(
            $namespace->getName(),
            static fn(): string => new FunctionProxyGenerator(
                $namespace,
                AbstractJoinpoint::flatAndSortAdvices($functionAdvices),
            )->generate(),
        );
        if ($includeProxy === null) {
            return false;
        }
        $editor->append($namespace->getLastTokenPosition(), PHP_EOL . $includeProxy);

        return true;
    }
}
