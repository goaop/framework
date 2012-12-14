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
use Go\Aop\Support\AopChildFactory;

use TokenReflection\Broker;
use TokenReflection\ReflectionClass;
use TokenReflection\ReflectionFileNamespace;

/**
 * @package go
 */
class AopProxyTransformer implements SourceTransformer
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
     * Constructs AOP Proxy transformer
     *
     * @param Broker $broker Instance of reflection broker to use
     */
    public function __construct(array $options, Broker $broker)
    {
        $this->broker       = $broker;
        $this->includePaths = array_map('realpath', $options['includePaths']);
    }

    /**
     * This method may transform the supplied source and return a new replacement for it
     *
     * @param string $source Source for class
     * @param StreamMetaData $metadata Metadata for source
     *
     * @return string Transformed source
     */
    public function transform($source, StreamMetaData $metadata = null)
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
                return $source;
            }
        }

        $parsedSource = $this->broker->processString($source, $fileName, true);

        /** @var $namespaces ReflectionFileNamespace[] */
        $namespaces = $parsedSource->getNamespaces();
        assert('count($namespaces) < 2; /* Only one namespace per file is supported */');

        foreach ($namespaces as $namespace) {

            /** @var $classes ReflectionClass[] */
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {

                // Skip interfaces
                if ($class->isInterface()) {
                    continue;
                }

                // Look for aspects
                if ($class->hasAnnotation('Aspect') || in_array('Go\Aop\Aspect', $class->getInterfaceNames())) {
                    // Here we will create an aspect advisor and register aspect
                    // AspectContainer::register($class);
                    continue;
                }

                $container = AspectKernel::getInstance()->getContainer();
                $advices   = $container->getAdvicesForClass($class);

                if ($advices) {

                    // Prepare new parent name
                    $newParentName = $class->getShortName() . AspectContainer::AOP_PROXIED_SUFFIX;

                    // Replace original class name with new
                    $source = $this->adjustOriginalClass($class, $source, $newParentName);

                    // Prepare child Aop proxy
                    $child  = AopChildFactory::generate($class, $advices);

                    // Set new parent name instead of original
                    $child->setParentName($newParentName);

                    // Add child to source
                    $source .= $child;
                }
            }
        }
        return $source;
    }

    /**
     * Adjust definition of original class source to enable extending
     *
     * @param ReflectionClass $class Instance of class reflection
     * @param string $source Source code
     * @param string $newParentName New name for the parent class
     *
     * @return string Replaced code for class
     */
    private function adjustOriginalClass($class, $source, $newParentName)
    {
        $source = preg_replace(
            '/class\s+(' . $class->getShortName() . ')/iS',
            "class $newParentName",
            $source
        );
        if ($class->isFinal()) {
            // Remove final from class, child will be final instead
            $source = str_replace('final class', 'class', $source);
        }
        return $source;
    }
}
