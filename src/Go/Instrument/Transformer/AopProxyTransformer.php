<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2011, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Instrument\Transformer;

use ReflectionProperty as Property;

use Go\Aop\Support\AbstractChildCreator;

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

    public function __construct(Broker $broker, \Go\Aop\PointcutAdvisor $advisor)
    {
        $this->broker = $broker;
        $this->advisor = $advisor;
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

        // TODO: this code only for debug, will be refactored
        $classFilter = $this->advisor->getPointcut()->getClassFilter();

        /** @var $namespaces ReflectionFileNamespace[] */
        $namespaces = $parsedSource->getNamespaces();
        foreach ($namespaces as $namespace) {

            /** @var $classes ReflectionClass[] */
            $classes = $namespace->getClasses();
            foreach ($classes as $class) {
                if ($classFilter->matches($class) && !$class->isInterface()) {
                    // echo "Matching class ", $class->getName(), "<br>\n";

                    $child  = new AbstractChildCreator($class, $class->getShortName());
                    $source = preg_replace(
                        '/class\s+(' . $class->getShortName() . ')/i',
                        'class $1' . self::AOP_PROXIED_SUFFIX,
                        $source
                    );
                    if ($class->isFinal()) {
                        // Remove final from class
                        $source = str_replace('final class', 'class', $source);
                    }

                    $child->setProperty(Property::IS_PRIVATE | Property::IS_STATIC, '__joinPoints', 'array()');
                    $child->setParentName($class->getShortName() . self::AOP_PROXIED_SUFFIX);

                    $methodMatcher = $this->advisor->getPointcut()->getPointFilter();

                    /** @var $methods ReflectionMethod[] */
                    $methods = $class->getMethods();
                    foreach ($methods as $method) {
                        if ($methodMatcher->matches($method) && !$method->isFinal() /* temporary disable override of final methods */) {

                            // echo "Matching method ", $method->getName(), "<br>\n";
                            $child->override($method->getName(), $this->getMethodBody($method));
                        }
                    }

                    $source .= $child;
                    $source .= '\Go\Aop\Support\AdvisorRegistry::injectAdvices("\\' . $class->getName() . '", "\\' . $class->getName() . self::AOP_PROXIED_SUFFIX . '");';
                }
            }
        }
        return $source;
    }

    /**
     * Creates definition for method body
     *
     * @param ReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    private function getMethodBody($method)
    {
        $link = $method->isStatic() ? 'null' : '$this';
        $args = join(', ', array_map(function ($param) {
            return '$' . $param->getName();
        }, $method->getParameters()));

        $args = $link . ($args ? ", $args" : '');
        $body = "return self::\$__joinPoints['method:' . __FUNCTION__]->__invoke($args);";
        return $body;
    }

}
