<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty as Property;

use Go\Aop\Advice;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Support\AdvisorRegistry;
use Go\Aop\Support\Invoker;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\ReflectionMethodInvocation;
use Go\Aop\Framework\ClosureMethodInvocation;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;


/**
 * AOP Factory that is used to generate child code from joinpoints
 */
class AopChildFactory extends AbstractChildCreator
{

    /**
     * Generates an child code by parent class reflection and joinpoints for it
     *
     * @param ReflectionClass|ParsedReflectionClass $parent Parent class reflection
     * @param array|Advice[] $advices List of advices for
     *
     * @return AopChildFactory
     */
    public static function generate($parent, array $advices)
    {
        $aopChild = new static($parent, $parent->getShortName());
        if (!empty($advices)) {
            $aopChild->addJoinpointsProperty($aopChild);

            foreach ($advices as $name => $value) {

                list ($type, $name) = explode(':', $name);
                switch ($type) {
                    case 'method':
                    case 'static':
                        $aopChild->overrideMethod($parent->getMethod($name));
                        break;
                }
            }
        }
        return $aopChild;
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string $aopChildClass Aop child proxy class
     *
     * @return void
     */
    public static function injectJoinpoints($aopChildClass)
    {
        $originalClass = $aopChildClass;
        $advices       = AdvisorRegistry::advise($originalClass);
        $joinPoints    = static::wrapWithJoinPoints($advices, $aopChildClass);

        $aopChildClass = new ReflectionClass($aopChildClass);

        /** @var $prop Property */
        $prop = $aopChildClass->getProperty('__joinPoints');
        $prop->setAccessible(true);
        $prop->setValue($joinPoints);
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[] $classAdvices Advices for specific class
     * @param string $className Name of the original class to use
     *
     * @return array|Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinPoints($classAdvices, $className)
    {
        $joinpoints = array();
        foreach ($classAdvices as $name => $advices) {

            // Fields use prop:$name format, so use this information
            if (strpos($name, AdvisorRegistry::PROPERTY_PREFIX) === 0) {
                $propertyName      = substr($name, strlen(AdvisorRegistry::PROPERTY_PREFIX));
                $joinpoints[$name] = new ClassFieldAccess($className, $propertyName, $advices);
            } elseif (strpos($name, AdvisorRegistry::METHOD_PREFIX) === 0) {
                $methodName        = substr($name, strlen(AdvisorRegistry::METHOD_PREFIX));

                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    $dynamicInvoker    = Invoker::getDynamicParent();
                    $joinpoints[$name] = new ClosureMethodInvocation($dynamicInvoker, $className, $methodName, $advices);
                } else {
                    // Add BC for calling static method without LSB for PHP < 5.4
                    $joinpoints[$name] = new ReflectionMethodInvocation($className, $methodName, $advices);
                }
            } elseif (strpos($name, AdvisorRegistry::STATIC_METHOD_PREFIX) === 0) {
                $methodName  = substr($name, strlen(AdvisorRegistry::STATIC_METHOD_PREFIX));

                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    $staticInvoker     = Invoker::getStatic();
                    $joinpoints[$name] = new ClosureMethodInvocation($staticInvoker, $className, $methodName, $advices);
                } else {
                    // Add BC for calling static method without LSB for PHP < 5.4
                    $joinpoints[$name] = new ReflectionMethodInvocation($className, $methodName, $advices);
                }
            }
        }
        return $joinpoints;
    }

    /**
     * Adds a definition for joinpoints private property in the class
     *
     * @return void
     */
    protected function addJoinpointsProperty()
    {
        $this->setProperty(
            Property::IS_PRIVATE | Property::IS_STATIC,
            '__joinPoints',
            'array()'
        );
    }

    /**
     * Override parent method with joinjpoint invocation
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Method reflection
     */
    protected function overrideMethod($method)
    {
        // temporary disable override of final methods
        if (!$method->isFinal()) {
            $this->override($method->getName(), $this->getJoinpointInvocationBody($method));
        }
    }

    /**
     * Creates definition for method body
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody($method)
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'get_called_class()' : '$this';
        $prefix   = $isStatic ? 'static' : 'method';

        $args = join(', ', array_map(function ($param) {
            return '$' . $param->getName();
        }, $method->getParameters()));

        $args = $scope . ($args ? ", $args" : '');
        $body = "return self::\$__joinPoints['$prefix:' . __FUNCTION__]->__invoke($args);";
        return $body;
    }

    public function __toString()
    {
        $self = get_called_class();
        return parent::__toString()
            // Inject advices on call
            . PHP_EOL
            . '\\' . $self . '::injectJoinpoints("' . $this->name . '");';
    }


}
