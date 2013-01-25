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
use ReflectionParameter as Parameter;
use ReflectionMethod as Method;

use TokenReflection\ReflectionClass as ParsedClass;
use TokenReflection\ReflectionParameter as ParsedParameter;
use TokenReflection\ReflectionMethod as ParsedMethod;

abstract class AbstractProxy
{

    /**
     * Indent for source code
     *
     * @var int
     */
    protected $indent = 4;

    /**
     * Parent class reflection
     *
     * @var null|ReflectionClass|ParsedClass
     */
    protected $class = null;

    /**
     * Name for the current class
     *
     * @var string
     */
    protected $name = '';

    /**
     * Parent class name, can be changed manually
     *
     * @var string
     */
    protected $parentClassName = null;

    /**
     * Source code for methods
     *
     * @var array Name of method => source code for it
     */
    protected $methodsCode = array();

    /**
     * List of additional interfaces to implement
     *
     * @var array
     */
    protected $interfaces = array();

    /**
     * List of additional traits for using
     *
     * @var array
     */
    protected $traits = array();

    /**
     * Source code for properties
     *
     * @var array Name of property => source code for it
     */
    protected $propertiesCode = array();

    /**
     * List of advices that are used for generation of child
     *
     * @var array
     */
    protected $advices = array();

    /**
     * Constructs abstract child class from Reflection
     *
     * @param ReflectionClass|ParsedClass $parentClass Reflection
     * @param string $thisName Name of the child class
     * @param array $advices List of advices
     *
     * @throws \InvalidArgumentException for invalid classes
     */
    public function __construct($parentClass, $thisName, array $advices = array())
    {
        if (!$parentClass instanceof ReflectionClass && !$parentClass instanceof ParsedClass) {
            throw new \InvalidArgumentException("Invalid argument for class");
        }
        $this->advices = $advices;
        $this->class   = $parentClass;
        $this->name    = $thisName;

        $this->parentClassName = $parentClass->getShortName();
    }

    /**
     * Updates parent name for child
     *
     * @param string $newParentName New class name
     *
     * @return AbstractProxy
     */
    public function setParentName($newParentName)
    {
        $this->parentClassName = $newParentName;
        return $this;
    }

    /**
     * Override parent method with new body
     *
     * @param string $methodName Method name to override
     * @param string $body New body for method
     *
     * @return AbstractProxy
     */
    public function override($methodName, $body)
    {
        $this->methodsCode[$methodName] = $this->getOverriddenMethod($this->class->getMethod($methodName), $body);
        return $this;
    }

    /**
     * Creates a method
     *
     * @param int $methodFlags See ReflectionMethod modifiers
     * @param string $methodName Name of the method
     * @param string $body Body of method
     * @param string $parameters Definition of parameters
     *
     * @return AbstractProxy
     */
    public function setMethod($methodFlags, $methodName, $body, $parameters)
    {
        $this->methodsCode[$methodName] = sprintf("%s%s function %s(%s)\n{\n%s\n}\n",
            "/**\n * Method was created automatically, do not change it manually\n */\n",
            join(' ', Reflection::getModifierNames($methodFlags)),
            $methodName,
            $parameters,
            $this->indent($body)
        );
        return $this;
    }

    /**
     * Add an interface for child
     *
     * @param string|ReflectionClass|ParsedClass $interface
     *
     * @throws \InvalidArgumentException If object is not an interface
     */
    public function addInterface($interface)
    {
        $interfaceName = $interface;
        if ($interface instanceof ReflectionClass || $interface instanceof ParsedClass) {
            if (!$interface->isInterface()) {
                throw new \InvalidArgumentException("Interface expected to add");
            }
            $interfaceName = $interface->name;
        }
        $this->interfaces[] = $interfaceName;
    }

    /**
     * Add a trait for child
     *
     * @param string|ReflectionClass|ParsedClass $trait
     *
     * @throws \InvalidArgumentException If object is not a trait
     */
    public function addTrait($trait)
    {
        $traitName = $trait;
        if ($trait instanceof ReflectionClass || $trait instanceof ParsedClass) {
            if (!$trait->isTrait()) {
                throw new \InvalidArgumentException("Trait expected to add");
            }
            $traitName = $trait->name;
        }
        $this->traits[] = $traitName;
    }
    /**
     * Creates a property
     *
     * @param int $propFlags See ReflectionProperty modifiers
     * @param string $propName Name of the property
     * @param null|string $defaultText Default value, should be string text!
     *
     * @return AbstractProxy
     */
    public function setProperty($propFlags, $propName, $defaultText = null)
    {
        $this->propertiesCode[$propName] = sprintf("%s%s $%s%s;\n",
            "/**\n *Property was created automatically, do not change it manually\n */\n",
            join(' ', Reflection::getModifierNames($propFlags)),
            $propName,
            is_string($defaultText) ? " = $defaultText" : ''
        );
        return $this;
    }

    /**
     * Returns text representation of class
     *
     * @return string
     */
    abstract public function __toString();

    /**
     * Creates a method code from Reflection
     *
     * @param Method|ParsedMethod $method Reflection for method
     * @param string $body Body of method
     *
     * @return string
     */
    protected function getOverriddenMethod($method, $body)
    {
        $code = sprintf("%s%s function %s%s(%s)\n{\n%s\n}\n",
            preg_replace('/ {4}|\t/', '', $method->getDocComment()) ."\n",
            join(' ', Reflection::getModifierNames($method->getModifiers())),
            $method->returnsReference() ? '&' : '',
            $method->name,
            join(', ', $this->getParameters($method->getParameters())),
            $this->indent($body)
        );
        return $code;
    }

    /**
     * Indent block of code
     *
     * @param string $text Non-indented text
     *
     * @return string Indented text
     */
    protected function indent($text)
    {
        $pad   = str_pad('', $this->indent, ' ');
        $lines = array_map(function ($line) use ($pad) {
            return $pad . $line;
        }, explode("\n", $text));
        return join("\n", $lines);
    }

    /**
     * Returns list of string representation of parameters
     *
     * @param array|Parameter[]|ParsedParameter[] $parameters List of parameters
     *
     * @return array
     */
    protected function getParameters(array $parameters)
    {
        $parameterDefinitions = array();
        foreach ($parameters as $parameter) {
            $parameterDefinitions[] = $this->getParameterCode($parameter);
        }
        return $parameterDefinitions;
    }

    /**
     * Return string representation of parameter
     *
     * @param Parameter|ParsedParameter $parameter Reflection parameter
     *
     * @return string
     */
    protected function getParameterCode($parameter)
    {
        $type = '';
        if ($parameter->isArray()) {
            $type = 'array';
        } elseif ($parameter->getClass()) {
            $type = '\\' . $parameter->getClass()->name;
        }
        $defaultValue = null;
        $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
        if ($isDefaultValueAvailable) {
            if ($parameter instanceof ParsedParameter) {
                $defaultValue = $parameter->getDefaultValueDefinition();
            } else {
                $defaultValue = var_export($parameter->getDefaultValue());
            }
        } elseif ($parameter->allowsNull()) {
            $defaultValue = 'null';
        }
        $code = sprintf('%s%s$%s%s',
            $type ? "$type " : '',
            $parameter->isPassedByReference() ? '&' : '',
            $parameter->name,
            $isDefaultValueAvailable ? (" = " . $defaultValue) : ''
        );
        return $code;
    }
}
