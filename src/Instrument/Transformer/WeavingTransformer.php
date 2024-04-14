<?php

declare(strict_types = 1);
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
use Go\Aop\Features;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AdviceMatcher;
use Go\Core\AdviceMatcherInterface;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\ParserReflection\ReflectionClass;
use Go\ParserReflection\ReflectionFile;
use Go\ParserReflection\ReflectionFileNamespace;
use Go\ParserReflection\ReflectionMethod;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\FunctionProxyGenerator;
use Go\Proxy\TraitProxyGenerator;

/**
 * Main transformer that performs weaving of aspects into the source code
 */
class WeavingTransformer extends BaseSourceTransformer
{
    /**
     * Advice matcher for class
     */
    protected AdviceMatcherInterface $adviceMatcher;

    /**
     * Should we use parameter widening for our decorators
     */
    protected bool $useParameterWidening = false;

    /**
     * Cache manager
     */
    private CachePathManager $cachePathManager;

    /**
     * Loader for aspects
     */
    protected AspectLoader $aspectLoader;

    /**
     * Constructs a weaving transformer
     */
    public function __construct(
        AspectKernel $kernel,
        AdviceMatcherInterface $adviceMatcher,
        CachePathManager $cachePathManager,
        AspectLoader $loader
    ) {
        parent::__construct($kernel);
        $this->adviceMatcher    = $adviceMatcher;
        $this->cachePathManager = $cachePathManager;
        $this->aspectLoader     = $loader;

        $this->useParameterWidening = $kernel->hasFeature(Features::PARAMETER_WIDENING);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata
     * @return string See RESULT_XXX constants in the interface
     */
    public function transform(StreamMetaData $metadata): string
    {
        $totalTransformations = 0;
        $parsedSource         = new ReflectionFile($metadata->uri, $metadata->syntaxTree);

        // Check if we have some new aspects that weren't loaded yet
        $unloadedAspects = $this->aspectLoader->getUnloadedAspects();
        if (!empty($unloadedAspects)) {
            $this->loadAndRegisterAspects($unloadedAspects);
        }
        $advisors = $this->container->getServicesByInterface(Advisor::class);

        $namespaces = $parsedSource->getFileNamespaces();

        foreach ($namespaces as $namespace) {
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {
                // Skip interfaces and aspects
                if ($class->isInterface() || in_array(Aspect::class, $class->getInterfaceNames(), true)) {
                    continue;
                }
                $wasClassProcessed = $this->processSingleClass(
                    $advisors,
                    $metadata,
                    $class,
                    $parsedSource->isStrictMode()
                );
                $totalTransformations += (integer) $wasClassProcessed;
            }
            $wasFunctionsProcessed = $this->processFunctions($advisors, $metadata, $namespace);
            $totalTransformations += (integer) $wasFunctionsProcessed;
        }

        $result = ($totalTransformations > 0) ? self::RESULT_TRANSFORMED : self::RESULT_ABSTAIN;

        return $result;
    }

    /**
     * Performs weaving of single class if needed, returns true if the class was processed
     *
     * @param Advisor[]       $advisors List of advisors
     * @param StreamMetaData  $metadata
     * @param ReflectionClass $class
     * @param bool            $useStrictMode If the source file used strict mode, the proxy should too
     * @return bool
     */
    private function processSingleClass(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionClass $class,
        bool $useStrictMode
    ): bool {
        $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);

        if (empty($advices)) {
            // Fast return if there aren't any advices for that class
            return false;
        }

        // Sort advices in advance to keep the correct order in cache, and leave only keys for the cache
        $advices = AbstractJoinpoint::flatAndSortAdvices($advices);

        // Prepare new class name
        $newClassName = $class->getShortName() . AspectContainer::AOP_PROXIED_SUFFIX;

        // Replace original class name with new
        $this->adjustOriginalClass($class, $advices, $metadata, $newClassName);
        $newParentName = $class->getNamespaceName() . '\\' . $newClassName;

        // Prepare child Aop proxy
        $childProxyGenerator = $class->isTrait()
            ? new TraitProxyGenerator($class, $newParentName, $advices, $this->useParameterWidening)
            : new ClassProxyGenerator($class, $newParentName, $advices, $this->useParameterWidening);

        $refNamespace = new ReflectionFileNamespace($class->getFileName(), $class->getNamespaceName());
        foreach ($refNamespace->getNamespaceAliases() as $fqdn => $alias) {
            // Either we have a string or Identifier node
            if ($alias !== null) {
                $childProxyGenerator->addUse($fqdn, (string) $alias);
            } else {
                $childProxyGenerator->addUse($fqdn);
            }
        }

        $childCode = $childProxyGenerator->generate();

        if ($useStrictMode) {
            $childCode = 'declare(strict_types=1);' . PHP_EOL . $childCode;
        }

        $contentToInclude = $this->saveProxyToCache($class, $childCode);

        // Get last token for this class
        $lastClassToken = $class->getNode()->getAttribute('endTokenPos');

        $metadata->tokenStream[$lastClassToken]->text .= PHP_EOL . $contentToInclude;

        return true;
    }

    /**
     * Adjust definition of original class source to enable extending
     *
     * @param array $advices List of class advices (used to check for final methods and make them non-final)
     */
    private function adjustOriginalClass(
        ReflectionClass $class,
        array $advices,
        StreamMetaData $streamMetaData,
        string $newClassName
    ): void {
        $classNode = $class->getNode();
        $position  = $classNode->getAttribute('startTokenPos');
        do {
            if (isset($streamMetaData->tokenStream[$position])) {
                $token = $streamMetaData->tokenStream[$position];
                // Remove final and following whitespace from the class, child will be final instead
                if ($token->id === T_FINAL) {
                    unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position+1]);
                }
                // First string is class/trait name
                if ($token->id === T_STRING) {
                    $streamMetaData->tokenStream[$position]->text = $newClassName;
                    // We have finished our job, can break this loop
                    break;
                }
            }
            ++$position;
        } while (true);

        foreach ($class->getMethods(ReflectionMethod::IS_FINAL) as $finalMethod) {
            if (!$finalMethod instanceof ReflectionMethod || $finalMethod->getDeclaringClass()->name !== $class->name) {
                continue;
            }
            $hasDynamicAdvice = isset($advices[AspectContainer::METHOD_PREFIX][$finalMethod->name]);
            $hasStaticAdvice  = isset($advices[AspectContainer::STATIC_METHOD_PREFIX][$finalMethod->name]);
            if (!$hasDynamicAdvice && !$hasStaticAdvice) {
                continue;
            }
            $methodNode = $finalMethod->getNode();
            $position   = $methodNode->getAttribute('startTokenPos');
            do {
                if (isset($streamMetaData->tokenStream[$position])) {
                    $token = $streamMetaData->tokenStream[$position];
                    // Remove final and following whitespace from the method, child will be final instead
                    if ($token->id === T_FINAL) {
                        unset($streamMetaData->tokenStream[$position], $streamMetaData->tokenStream[$position+1]);
                        break;
                    }
                }
                ++$position;
            } while (true);
        }
    }

    /**
     * Performs weaving of functions in the current namespace, returns true if functions were processed, false otherwise
     *
     * @param Advisor[] $advisors List of advisors
     */
    private function processFunctions(
        array $advisors,
        StreamMetaData $metadata,
        ReflectionFileNamespace $namespace
    ): bool {
        static $cacheDirSuffix = '/_functions/';

        $wasProcessedFunctions = false;
        $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        $cacheDir        = $this->cachePathManager->getCacheDir();
        if (!empty($functionAdvices)) {
            $cacheDir .= $cacheDirSuffix;
            $fileName = str_replace('\\', '/', $namespace->getName()) . '.php';

            $functionFileName = $cacheDir . $fileName;
            if (!file_exists($functionFileName) || !$this->container->hasAnyResourceChangedSince(filemtime($functionFileName))) {
                $functionAdvices = AbstractJoinpoint::flatAndSortAdvices($functionAdvices);
                $dirname         = dirname($functionFileName);
                if (!file_exists($dirname)) {
                    mkdir($dirname, $this->options['cacheFileMode'], true);
                }
                $generator = new FunctionProxyGenerator($namespace, $functionAdvices, $this->useParameterWidening);
                file_put_contents($functionFileName, $generator->generate(), LOCK_EX);
                // For cache files we don't want executable bits by default
                chmod($functionFileName, $this->options['cacheFileMode'] & (~0111));
            }
            $content = 'include_once AOP_CACHE_DIR . ' . var_export($cacheDirSuffix . $fileName, true) . ';';

            $lastTokenPosition = $namespace->getLastTokenPosition();
            $metadata->tokenStream[$lastTokenPosition]->text .= PHP_EOL . $content;
            $wasProcessedFunctions = true;
        }

        return $wasProcessedFunctions;
    }

    /**
     * Save AOP proxy to the separate file anr returns the php source code for inclusion
     */
    private function saveProxyToCache(ReflectionClass $class, string $childCode): string
    {
        static $cacheDirSuffix = '/_proxies/';

        $cacheDir          = $this->cachePathManager->getCacheDir() . $cacheDirSuffix;
        $relativePath      = str_replace($this->options['appDir'] . DIRECTORY_SEPARATOR, '', $class->getFileName());
        $proxyRelativePath = str_replace('\\', '/', $relativePath . '/' . $class->getName() . '.php');
        $proxyFileName     = $cacheDir . $proxyRelativePath;
        $dirname           = dirname($proxyFileName);
        if (!file_exists($dirname)) {
            mkdir($dirname, $this->options['cacheFileMode'], true);
        }

        $body = '<?php' . PHP_EOL . $childCode;

        $isVirtualSystem = strpos($proxyFileName, 'vfs') === 0;
        file_put_contents($proxyFileName, $body, $isVirtualSystem ? 0 : LOCK_EX);
        // For cache files we don't want executable bits by default
        chmod($proxyFileName, $this->options['cacheFileMode'] & (~0111));

        return 'include_once AOP_CACHE_DIR . ' . var_export($cacheDirSuffix . $proxyRelativePath, true) . ';';
    }

    /**
     * Utility method to load and register unloaded aspects
     *
     * @param array $unloadedAspects List of unloaded aspects
     */
    private function loadAndRegisterAspects(array $unloadedAspects): void
    {
        foreach ($unloadedAspects as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }
    }
}
