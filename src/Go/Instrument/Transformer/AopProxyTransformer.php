<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use ReflectionProperty as Property;

use Go\Aop\Support\AdvisorRegistry;
use Go\Aop\Support\AopChildFactory;

use TokenReflection\Broker;
use TokenReflection\ReflectionClass;
use TokenReflection\ReflectionMethod;
use TokenReflection\ReflectionFileNamespace;

/**
 * @package go
 */
class AopProxyTransformer implements SourceTransformer
{

    /**
     * Suffix, that will be added to all proxied class names
     */
    const AOP_PROXIED_SUFFIX = '__AopProxied';

    /**
     * Reflection broker instance
     *
     * @var Broker
     */
    protected $broker;

    public function __construct(Broker $broker)
    {
        $this->broker = $broker;
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
        $parsedSource = $this->broker->processString($source, $metadata->getResourceUri(), true);

        /** @var $namespaces ReflectionFileNamespace[] */
        $namespaces = $parsedSource->getNamespaces();
        foreach ($namespaces as $namespace) {

            /** @var $classes ReflectionClass[] */
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {

                $joinpoints = AdvisorRegistry::advise($class);

                if ($joinpoints && !$class->isInterface()) {
                    // Prepare new parent name
                    $newParentName = $class->getShortName() . self::AOP_PROXIED_SUFFIX;

                    // Replace original class name with new
                    $source = $this->adjustOriginalClass($class, $source, $newParentName);

                    // Prepare child Aop proxy
                    $child  = AopChildFactory::generate($class, $joinpoints);

                    // Set new parent name instead of original
                    $child->setParentName($newParentName);

                    // Add child to source
                    $source .= $child;

                    $source .= '\Go\Aop\Support\AdvisorRegistry::injectAdvices("\\' . $class->getName() . '", "\\' . $class->getName() . self::AOP_PROXIED_SUFFIX . '");';
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
            '/class\s+(' . $class->getShortName() . ')/i',
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
