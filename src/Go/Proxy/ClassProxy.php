<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Proxy;

use Reflection;
use ReflectionClass;
use ReflectionMethod as Method;
use ReflectionParameter as Parameter;
use ReflectionProperty as Property;
use UnexpectedValueException;

use Go\Aop\Advice;
use Go\Aop\IntroductionInfo;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\ReflectionMethodInvocation;
use Go\Aop\Framework\ClosureStaticMethodInvocation;
use Go\Aop\Framework\ClosureDynamicMethodInvocation;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionMethod as ParsedMethod;
use TokenReflection\ReflectionParameter as ParsedParameter;
use TokenReflection\ReflectionProperty as ParsedProperty;

/**
 * AOP Factory that is used to generate child code from joinpoints
 */
class ClassProxy extends AbstractProxy
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
     * @param ReflectionClass|ParsedClass $parent Parent class reflection
     * @param array|Advice[] $advices List of advices for
     *
     * @return ClassProxy
     */
    public static function generate($parent, array $advices)
    {
        $aopChild = new self($parent, $parent->getShortName(), $advices);
        $aopChild->addInterface('\Go\Aop\Proxy');

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
     * @param string $className Aop child proxy class
     * @param array|Advice[] $advices List of advices to inject into class
     *
     * @return void
     */
    public static function injectJoinPoints($className, array $advices = array())
    {
        if (!$advices) {
            $container = AspectKernel::getInstance()->getContainer();
            $advices   = $container->getAdvicesForClass($className);
        }

        $className  = new ReflectionClass($className);
        $joinPoints = static::wrapWithJoinPoints($advices, $className->getParentClass()->name);

        /** @var $prop Property */
        $prop = $className->getProperty('__joinPoints');
        $prop->setAccessible(true);
        $prop->setValue($joinPoints);
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[] $classAdvices Advices for specific class
     * @param string $className Name of the original class to use
     *
     * @throws \UnexpectedValueException If joinPoint type is unknown
     *
     * @todo Extension should be responsible for wrapping advice with join point.
     *
     * @return array|Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinPoints($classAdvices, $className)
    {
        $joinPoints = array();
        foreach ($classAdvices as $name => $advices) {

            $joinPoint = static::wrapSingleJoinPoint($className, $name, $advices);
            if ($joinPoint) {
                $joinPoints[$name] = $joinPoint;
            }

        }
        return $joinPoints;
    }

    /**
     * Wrap advices with single join point
     *
     * @param string $className Name of the original class to use
     * @param string $name Unique joinpoint name
     * @param array|Advice[] $advices Advices for specific class
     *
     * @throws \UnexpectedValueException
     *
     * @return array|Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapSingleJoinPoint($className, $name, $advices)
    {
        list ($joinPointType, $joinPointName) = explode(':', $name);
        $joinPoint = null;
        switch ($joinPointType) {
            case AspectContainer::METHOD_PREFIX:
                if (IS_MODERN_PHP) {
                    $joinPoint = new ClosureDynamicMethodInvocation($className, $joinPointName, $advices);
                } else {
                    $joinPoint = new ReflectionMethodInvocation($className, $joinPointName, $advices);
                }
                break;

            case AspectContainer::STATIC_METHOD_PREFIX:
                if (IS_MODERN_PHP) {
                    $joinPoint = new ClosureStaticMethodInvocation($className, $joinPointName, $advices);
                } else {
                    $joinPoint = new ReflectionMethodInvocation($className, $joinPointName, $advices);
                }
                break;

            case AspectContainer::PROPERTY_PREFIX:
                $joinPoint = new ClassFieldAccess($className, $joinPointName, $advices);
                break;

            case AspectContainer::INTRODUCTION_TRAIT_PREFIX:
                // Nothing to create here
                break;

            default:
                throw new UnexpectedValueException("Invalid joinpoint `{$joinPointType}` type. Not yet supported.");
        }
        return $joinPoint;
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
     * @param Method|ParsedMethod $method Method reflection
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
     * @param Method|ParsedMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody($method)
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'get_called_class()' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $args = join(', ', array_map(function ($param) {
            /** @var $param Parameter|ParsedParameter */
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
     * @param Property|ParsedProperty $property Reflection of property to intercept
     */
    protected function interceptProperty($property)
    {
        $this->interceptedProperties[] = is_object($property) ? $property->name : $property;
        $this->isFieldsIntercepted = true;
    }

    /**
     * {@inheritDoc}
     */
    public function __toString()
    {
        $ctor = $this->class->getConstructor();
        if ($this->isFieldsIntercepted && (!$ctor || !$ctor->isPrivate())) {
            $this->addFieldInterceptorsCode($ctor);
        }
        $serialized = serialize($this->advices);

        ksort($this->methodsCode);
        ksort($this->propertiesCode);
        $prefix = join(' ', Reflection::getModifierNames($this->class->getModifiers()));

        $classCode = sprintf("%s\n%sclass %s extends %s%s\n{\n%s\n\n%s\n%s\n}",
            $this->class->getDocComment(),
            $prefix ? "$prefix " : '',
            $this->name,
            $this->parentClassName,
            $this->interfaces ? ' implements ' . join(', ', $this->interfaces) : '',
            $this->traits ? $this->indent('use ' . join(', ', $this->traits) .';') : '',
            $this->indent(join("\n", $this->propertiesCode)),
            $this->indent(join("\n", $this->methodsCode))
        );

        return $classCode
            // Inject advices on call
            . PHP_EOL
            . '\\' . __CLASS__ . "::injectJoinPoints('" . $this->class->name . "', unserialize('{$serialized}'));";
    }

    /**
     * Add code for intercepting properties
     *
     * @param null|Method|ParsedMethod $constructor Constructor reflection or null
     */
    protected function addFieldInterceptorsCode($constructor = null)
    {
        $this->setProperty(Property::IS_PRIVATE, '__properties', 'array()');
        $this->setMethod(Method::IS_PUBLIC, '__get', $this->getMagicGetterBody(), '$name');
        $this->setMethod(Method::IS_PUBLIC, '__set', $this->getMagicSetterBody(), '$name, $value');
        $this->isFieldsIntercepted = true;
        if ($constructor) {
            $this->override('__construct', $this->getConstructorBody($constructor, true));
        } else {
            $this->setMethod(
                Method::IS_PUBLIC,
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
     * @param ParsedMethod|Method|null $constructor Constructor reflection
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
