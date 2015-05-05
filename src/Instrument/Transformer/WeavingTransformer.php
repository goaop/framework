<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Instrument\Transformer;

use Go\Aop\Advisor;
use Go\Aop\Features;
use Go\Aop\Framework\AbstractJoinpoint;
use Go\Core\AspectContainer;
use Go\Core\AdviceMatcher;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Instrument\CleanableMemory;
use Go\Proxy\ClassProxy;
use Go\Proxy\FunctionProxy;
use Go\Proxy\TraitProxy;
use TokenReflection\Broker;
use TokenReflection\Exception\FileProcessingException;
use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionFileNamespace as ParsedFileNamespace;

/**
 * Main transformer that performs weaving of aspects into the source code
 */
class WeavingTransformer extends BaseSourceTransformer
{

    /**
     * Reflection broker instance
     *
     * @var Broker
     */
    protected $broker;

    /**
     * @var AdviceMatcher
     */
    protected $adviceMatcher;

    /**
     * Instance of aspect loader
     *
     * @var AspectLoader
     */
    protected $aspectLoader;

    /**
     * Constructs a weaving transformer
     *
     * @param AspectKernel $kernel Instance of aspect kernel
     * @param Broker $broker Instance of reflection broker to use
     * @param AdviceMatcher $matcher Advice matcher for class
     * @param AspectLoader $loader Loader for aspects
     */
    public function __construct(AspectKernel $kernel, Broker $broker, AdviceMatcher $matcher, AspectLoader $loader)
    {
        parent::__construct($kernel);
        $this->broker        = $broker;
        $this->adviceMatcher = $matcher;
        $this->aspectLoader  = $loader;
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void|bool Return false if transformation should be stopped
     */
    public function transform(StreamMetaData $metadata)
    {
        $totalTransformations = 0;

        $fileName = $metadata->uri;

        try {
            CleanableMemory::enterProcessing();
            $parsedSource = $this->broker->processString($metadata->source, $fileName, true);
        } catch (FileProcessingException $e) {
            CleanableMemory::leaveProcessing();

            return false;
        }

        // Check if we have some new aspects that weren't loaded yet
        $unloadedAspects = $this->aspectLoader->getUnloadedAspects();
        if ($unloadedAspects) {
            $this->loadAndRegisterAspects($unloadedAspects);
        }
        $advisors = $this->container->getByTag('advisor');

        /** @var $namespaces ParsedFileNamespace[] */
        $namespaces = $parsedSource->getNamespaces();
        $lineOffset = 0;

        foreach ($namespaces as $namespace) {

            /** @var $classes ParsedClass[] */
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {

                $parentClassNames = array_merge(
                    $class->getParentClassNameList(),
                    $class->getInterfaceNames(),
                    $class->getTraitNames()
                );

                foreach ($parentClassNames as $parentClassName) {
                    class_exists($parentClassName); // trigger autoloading of class/interface/trait
                }

                // Skip interfaces and aspects
                if ($class->isInterface() || in_array('Go\Aop\Aspect', $class->getInterfaceNames())) {
                    continue;
                }
                $wasClassProcessed    = $this->processSingleClass($advisors, $metadata, $class, $lineOffset);
                $totalTransformations += (integer) $wasClassProcessed;
            }
            $wasFunctionsProcessed = $this->processFunctions($advisors, $metadata, $namespace);
            $totalTransformations  += (integer) $wasFunctionsProcessed;
        }

        CleanableMemory::leaveProcessing();

        // If we return false this will indicate no more transformation for following transformers
        return $totalTransformations > 0;
    }

    /**
     * Performs weaving of single class if needed
     *
     * @param array|Advisor[] $advisors
     * @param StreamMetaData $metadata Source stream information
     * @param ParsedClass $class Instance of class to analyze
     * @param integer $lineOffset Current offset, will be updated to store the last position
     *
     * @return bool True if was class processed, false otherwise
     */
    private function processSingleClass(array $advisors, StreamMetaData $metadata, ParsedClass $class, &$lineOffset)
    {
        $advices = $this->adviceMatcher->getAdvicesForClass($class, $advisors);

        if (!$advices) {
            // Fast return if there aren't any advices for that class
            return false;
        }

        // Sort advices in advance to keep the correct order in cache
        foreach ($advices as &$typeAdvices) {
            foreach ($typeAdvices as &$joinpointAdvices) {
                if (is_array($joinpointAdvices)) {
                    $joinpointAdvices = AbstractJoinpoint::sortAdvices($joinpointAdvices);
                }
            }
        }

        // Prepare new parent name
        $newParentName = $class->getShortName() . AspectContainer::AOP_PROXIED_SUFFIX;

        // Replace original class name with new
        $metadata->source = $this->adjustOriginalClass($class, $metadata->source, $newParentName);

        // Prepare child Aop proxy
        $useStatic = $this->kernel->hasFeature(Features::USE_STATIC_FOR_LSB);
        $child     = ($this->kernel->hasFeature(Features::USE_TRAIT) && $class->isTrait())
            ? new TraitProxy($class, $advices, $useStatic)
            : new ClassProxy($class, $advices, $useStatic);

        // Set new parent name instead of original
        $child->setParentName($newParentName);
        $contentToInclude = $this->saveProxyToCache($class, $child);

        // Add child to source
        $tokenCount = $class->getBroker()->getFileTokens($class->getFileName())->count();
        if ($tokenCount - $class->getEndPosition() < 3) {
            // If it's the last class in a file, just add child source
            $metadata->source .= $contentToInclude . PHP_EOL;
        } else {
            $lastLine  = $class->getEndLine() + $lineOffset; // returns the last line of class
            $dataArray = explode("\n", $metadata->source);

            $currentClassArray = array_splice($dataArray, 0, $lastLine);
            $childClassArray   = explode("\n", $contentToInclude);
            $lineOffset += count($childClassArray) + 2; // returns LoC for child class + 2 blank lines

            $dataArray = array_merge($currentClassArray, array(''), $childClassArray, array(''), $dataArray);

            $metadata->source = implode("\n", $dataArray);
        }

        return true;
    }

    /**
     * Adjust definition of original class source to enable extending
     *
     * @param ParsedClass $class Instance of class reflection
     * @param string $source Source code
     * @param string $newParentName New name for the parent class
     *
     * @return string Replaced code for class
     */
    private function adjustOriginalClass($class, $source, $newParentName)
    {
        $type = ($this->kernel->hasFeature(Features::USE_TRAIT) && $class->isTrait()) ? 'trait' : 'class';
        $source = preg_replace(
            "/{$type}\s+(" . $class->getShortName() . ')(\b)/iS',
            "{$type} {$newParentName}$2",
            $source
        );
        if ($class->isFinal()) {
            // Remove final from class, child will be final instead
            $source = str_replace("final {$type}", $type, $source);
        }

        return $source;
    }

    /**
     * Performs weaving of functions in the current namespace
     *
     * @param array|Advisor[] $advisors List of advisors
     * @param StreamMetaData $metadata Source stream information
     * @param ParsedFileNamespace $namespace Current namespace for file
     *
     * @return boolean True if functions were processed, false otherwise
     */
    private function processFunctions(array $advisors, StreamMetaData $metadata, $namespace)
    {
        $wasProcessedFunctions = false;
        $functionAdvices = $this->adviceMatcher->getAdvicesForFunctions($namespace, $advisors);
        if ($functionAdvices && $this->options['cacheDir']) {
            $cacheDirSuffix = '/_functions/';
            $cacheDir       = $this->options['cacheDir'] . $cacheDirSuffix;
            $fileName       = str_replace('\\', '/', $namespace->getName()) . '.php';

            $functionFileName = $cacheDir . $fileName;
            if (!file_exists($functionFileName) || !$this->container->isFresh(filemtime($functionFileName))) {
                $dirname = dirname($functionFileName);
                if (!file_exists($dirname)) {
                    mkdir($dirname, 0770, true);
                }
                $source = new FunctionProxy($namespace, $functionAdvices);
                file_put_contents($functionFileName, $source);
            }
            $content = 'include_once AOP_CACHE_DIR . ' . var_export($cacheDirSuffix . $fileName, true) . ';' . PHP_EOL;
            $metadata->source .= $content;
            $wasProcessedFunctions = true;
        }

        return $wasProcessedFunctions;
    }

    /**
     * Save AOP proxy to the separate file anr returns the php source code for inclusion
     *
     * @param ParsedClass $class Original class reflection
     * @param string|ClassProxy $child
     *
     * @return string
     */
    private function saveProxyToCache($class, $child)
    {
        // Without cache we should rewrite original file
        if (empty($this->options['cacheDir'])) {
            return $child;
        }
        $cacheDirSuffix = '/_proxies/';
        $cacheDir       = $this->options['cacheDir'] . $cacheDirSuffix;
        $fileName       = str_replace($this->options['appDir'] . DIRECTORY_SEPARATOR, '', $class->getFileName());

        $proxyFileName = $cacheDir . $fileName;
        $dirname       = dirname($proxyFileName);
        if (!file_exists($dirname)) {
            mkdir($dirname, 0770, true);
        }

        $body      = '<?php' . PHP_EOL;
        $namespace = $class->getNamespaceName();
        if ($namespace) {
            $body .= "namespace {$namespace};" . PHP_EOL . PHP_EOL;
        }
        foreach ($class->getNamespaceAliases() as $alias => $fqdn) {
            $body .= "use {$fqdn} as {$alias};" . PHP_EOL;
        }
        $body .= $child;
        file_put_contents($proxyFileName, $body);

        return 'include_once AOP_CACHE_DIR . ' . var_export($cacheDirSuffix . $fileName, true) . ';' . PHP_EOL;
    }

    /**
     * Utility method to load and register unloaded aspects
     *
     * @param array $unloadedAspects List of unloaded aspects
     */
    private function loadAndRegisterAspects(array $unloadedAspects)
    {
        foreach ($unloadedAspects as $unloadedAspect) {
            $this->aspectLoader->loadAndRegister($unloadedAspect);
        }
    }
}
