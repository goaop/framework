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
use ReflectionParameter;
use ReflectionProperty as Property;
use UnexpectedValueException;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Aop\Advice;
use Go\Aop\IntroductionInfo;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\ReflectionMethodInvocation;
use Go\Aop\Framework\ClosureStaticMethodInvocation;
use Go\Aop\Framework\ClosureDynamicMethodInvocation;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;
use TokenReflection\ReflectionParameter as ParsedReflectionParameter;
use TokenReflection\ReflectionProperty as ParsedReflectionProperty;

/**
 * AOP Factory that is used to generate child code from joinpoints
 */
class AopChildFactory extends AbstractChildCreator
{

    /**
     * Flag to determine if we need to add a code for property interceptors
     *
     * @var bool
     */
    private $isFieldsIntercepted = false;

    /**
     * List of intercepted properties names
     *
     * @var array
     */
    private $interceptedProperties = array();

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
        $aopChild = new static($parent, $parent->getShortName(), $advices);
        if (!empty($advices)) {
            $aopChild->addJoinpointsProperty($aopChild);

            foreach ($advices as $name => $value) {

                list ($type, $pointName) = explode(':', $name, 2);
                switch ($type) {
                    case AspectContainer::METHOD_PREFIX:
                    case AspectContainer::STATIC_METHOD_PREFIX:
                        $aopChild->overrideMethod($parent->getMethod($pointName));
                        break;

                    case AspectContainer::PROPERTY_PREFIX:
                        $aopChild->interceptProperty($parent->getProperty($pointName));
                        break;

                    case AspectContainer::INTRODUCTION_TRAIT_PREFIX:
                        /** @var $value IntroductionInfo */
                        foreach ($value->getInterfaces() as $interface) {
                            $aopChild->addInterface($interface);
                        }
                        foreach ($value->getTraits() as $trait) {
                            $aopChild->addTrait($trait);
                        }
                        break;

                    default:
                        throw new \InvalidArgumentException("Unsupported point `$type`");
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
    public static function injectJoinpoints($aopChildClass, array $advices = array())
    {
        if (!$advices) {
            $container = AspectKernel::getInstance()->getContainer();
            $advices   = $container->getAdvicesForClass($aopChildClass);
        }

        $aopChildClass = new ReflectionClass($aopChildClass);
        $joinPoints    = static::wrapWithJoinPoints($advices, $aopChildClass->getParentClass()->name);

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

            list ($joinPointType, $joinPointName) = explode(':', $name);

            switch ($joinPointType) {
                case AspectContainer::METHOD_PREFIX:
                    if (IS_MODERN_PHP) {
                        $joinpoint = new ClosureDynamicMethodInvocation($className, $joinPointName, $advices);
                    } else {
                        $joinpoint = new ReflectionMethodInvocation($className, $joinPointName, $advices);
                    }
                    break;

                case AspectContainer::STATIC_METHOD_PREFIX:
                    if (IS_MODERN_PHP) {
                        $joinpoint = new ClosureStaticMethodInvocation($className, $joinPointName, $advices);
                    } else {
                        $joinpoint = new ReflectionMethodInvocation($className, $joinPointName, $advices);
                    }
                    break;

                case AspectContainer::PROPERTY_PREFIX:
                    $joinpoint = new ClassFieldAccess($className, $joinPointName, $advices);
                    break;

                case AspectContainer::INTRODUCTION_TRAIT_PREFIX:
                    continue;

                default:
                    throw new UnexpectedValueException("Invalid joinpoint `{$joinPointType}` type. Not yet supported.");
            }
            $joinpoints[$name] = $joinpoint;

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
     * Override parent method with joinpoint invocation
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Method reflection
     */
    protected function overrideMethod($method)
    {
        // temporary disable override of final methods
        if (!$method->isFinal() && !$method->isAbstract()) {
            $this->override($method->name, $this->getJoinpointInvocationBody($method));
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
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $args = join(', ', array_map(function ($param) {
            /** @var $param ReflectionParameter|ParsedReflectionParameter */
            $byReference = $param->isPassedByReference() ? '&' : '';
            return $byReference . '$' . $param->name;
        }, $method->getParameters()));

        $args = $scope . ($args ? ", array($args)" : '');
        $body = "return self::\$__joinPoints['{$prefix}:{$method->name}']->__invoke($args);";
        return $body;
    }

    /**
     * Makes property intercepted
     *
     * @param Property|ParsedReflectionProperty $property Reflection of property to intercept
     */
    protected function interceptProperty($property)
    {
        $this->interceptedProperties[] = is_object($property) ? $property->name : $property;
        $this->isFieldsIntercepted = true;
    }

    public function __toString()
    {
        $ctor = $this->class->getConstructor();
        if ($this->isFieldsIntercepted && (!$ctor || !$ctor->isPrivate())) {
            $this->addFieldInterceptorsCode($ctor);
        }
        $self       = get_called_class();
        $serialized = serialize($this->advices);
        return parent::__toString()
            // Inject advices on call
            . PHP_EOL
            . '\\' . $self . "::injectJoinpoints('" . $this->class->name . "', unserialize('{$serialized}'));";
    }

    /**
     * Add code for intercepting properties
     *
     * @param null|ReflectionMethod|ParsedReflectionMethod $constructor Constructor reflection or null
     */
    protected function addFieldInterceptorsCode($constructor = null)
    {
        $this->setProperty(Property::IS_PRIVATE, '__properties', 'array()');
        $this->setMethod(ReflectionMethod::IS_PUBLIC, '__get', $this->getMagicGetterBody(), '$name');
        $this->setMethod(ReflectionMethod::IS_PUBLIC, '__set', $this->getMagicSetterBody(), '$name, $value');
        $this->isFieldsIntercepted = true;
        if ($constructor) {
            $this->override('__construct', $this->getConstructorBody($constructor, true));
        } else {
            $this->setMethod(
                ReflectionMethod::IS_PUBLIC,
                '__construct',
                $this->getConstructorBody($constructor, false),
                ''
            );
        }
    }

    /**
     * Returns a code for magic getter to perform interception
     *
     * @return string
     */
    private function getMagicGetterBody()
    {
        return <<<'GETTER'
if (array_key_exists($name, $this->__properties)) {
    return self::$__joinPoints["prop:$name"]->__invoke(
        $this,
        \Go\Aop\Intercept\FieldAccess::READ,
        $this->__properties[$name]
    );
} elseif (method_exists(get_parent_class(), __FUNCTION__)) {
    return parent::__get($name);
} else {
    trigger_error("Trying to access undeclared property {$name}");
    return null;
}
GETTER;
    }

    /**
     * Returns a code for magic setter to perform interception
     *
     * @return string
     */
    private function getMagicSetterBody()
    {
        return <<<'SETTER'
if (array_key_exists($name, $this->__properties)) {
    $this->__properties[$name] = self::$__joinPoints["prop:$name"]->__invoke(
        $this,
        \Go\Aop\Intercept\FieldAccess::WRITE,
        $this->__properties[$name],
        $value
    );
} elseif (method_exists(get_parent_class(), __FUNCTION__)) {
    parent::__set($name, $value);
} else {
    $this->$name = $value;
}
SETTER;
    }

    /**
     * Returns constructor code
     *
     * @param ParsedReflectionMethod|ReflectionMethod|null $constructor Constructor reflection
     * @param bool $isCallParent Is there is a need to call parent code
     *
     * @return string
     */
    private function getConstructorBody($constructor, $isCallParent)
    {
        $assocProperties = array();
        $listProperties  = array();
        foreach ($this->interceptedProperties as $propertyName) {
            $assocProperties[] = "'$propertyName' => \$this->$propertyName";
            $listProperties[]  = "\$this->$propertyName";
        }
        $assocProperties = $this->indent(join(',' . PHP_EOL, $assocProperties));
        $listProperties  = $this->indent(join(',' . PHP_EOL, $listProperties));
        if (isset($this->methodsCode['__construct'])) {
            $parentCall = $this->getJoinpointInvocationBody($constructor);
        } elseif ($isCallParent) {
            $parentCall = "call_user_func_array(array('parent', __FUNCTION__), func_get_args());";
        } else {
            $parentCall = '';
        }
        return <<<CTOR
\$this->__properties = array(
$assocProperties
);
unset(
$listProperties
);
$parentCall
CTOR;
    }
}
