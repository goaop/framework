<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Proxy\ClassProxy;

use Go\Proxy\TraitProxy;
use TokenReflection\Broker;
use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionFileNamespace as ParsedFileNamespace;

/**
 * @package go
 */
class WeavingTransformer implements SourceTransformer
{

    /**
     * Reflection broker instance
     *
     * @var Broker
     */
    protected $broker;

    /**
     * List of include paths to process
     *
     * @var array
     */
    protected $includePaths = array();

    /**
     * List of exclude paths to process
     *
     * @var array
     */
    protected $excludePaths = array();

    /**
     * Constructs AOP Proxy transformer
     *
     * @param Broker $broker Instance of reflection broker to use
     */
    public function __construct(array $options, Broker $broker)
    {
        $this->broker       = $broker;
        $this->includePaths = array_map('realpath', $options['includePaths']);
        $this->excludePaths = array_map('realpath', $options['autoload']);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param StreamMetaData $metadata Metadata for source
     * @return void
     */
    public function transform(StreamMetaData $metadata)
    {
        $fileName = realpath($metadata->getResourceUri());
        if ($this->includePaths) {
            $found = false;
            foreach ($this->includePaths as $includePath) {
                if (strpos($fileName, $includePath) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return;
            }
        }

        foreach ($this->excludePaths as $excludePath) {
            if (strpos($fileName, $excludePath) === 0) {
                return;
            }
        }


        $parsedSource = $this->broker->processString($metadata->source, $fileName, true);

        /** @var $namespaces ParsedFileNamespace[] */
        $namespaces = $parsedSource->getNamespaces();
        assert('count($namespaces) < 2; /* Only one namespace per file is supported */');

        foreach ($namespaces as $namespace) {

            /** @var $classes ParsedClass[] */
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {

                // Skip interfaces
                if ($class->isInterface()) {
                    continue;
                }

                // Look for aspects
                if ($class->implementsInterface('Go\Aop\Aspect')) {
                    continue;
                }

                $container = AspectKernel::getInstance()->getContainer();
                $advices   = $container->getAdvicesForClass($class);

                if ($advices) {

                    // Prepare new parent name
                    $newParentName = $class->getShortName() . AspectContainer::AOP_PROXIED_SUFFIX;

                    // Replace original class name with new
                    $metadata->source = $this->adjustOriginalClass($class, $metadata->source, $newParentName);

                    // Prepare child Aop proxy
                    $child = $class->isTrait()
                            ? TraitProxy::generate($class, $advices)
                            : ClassProxy::generate($class, $advices);

                    // Set new parent name instead of original
                    $child->setParentName($newParentName);

                    // Add child to source
                    $metadata->source .= $child;
                }
            }
        }
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
        $type = $class->isTrait() ? 'trait' : 'class';
        $source = preg_replace(
            "/{$type}\s+(" . $class->getShortName() . ')/iS',
            "{$type} {$newParentName}",
            $source
        );
        if ($class->isFinal()) {
            // Remove final from class, child will be final instead
            $source = str_replace("final {$type}", $type, $source);
        }
        return $source;
    }
}
