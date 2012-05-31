<?php
/**
 * Go! OOP&AOP PHP framework
 *
 * @copyright     Copyright 2012, Lissachenko Alexander <lisachenko.it@gmail.com>
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Go\Aop\Support;

use Reflection;
use ReflectionClass;
use ReflectionParameter;
use ReflectionMethod;

use TokenReflection\ReflectionClass as ParsedReflectionClass;
use TokenReflection\ReflectionParameter as ParsedReflectionParameter;
use TokenReflection\ReflectionMethod as ParsedReflectionMethod;

class AbstractChildCreator
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
     * @var null|ReflectionClass|ParsedReflectionClass
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
     * Source code for properties
     *
     * @var array Name of property => source code for it
     */
    protected $propertiesCode = array();

    /**
     * Constructs abstract child class from Reflection
     *
     * @param ReflectionClass|ParsedReflectionClass $parentClass Reflection
     * @param string $thisName Name of the child class
     *
     * @throws \InvalidArgumentException for invalid classes
     */
    public function __construct($parentClass, $thisName)
    {
        if (!$parentClass instanceof ReflectionClass && !$parentClass instanceof ParsedReflectionClass) {
            throw new \InvalidArgumentException("Invalid argument for class");
        }
        $this->class = $parentClass;
        $this->name  = $thisName;

        $this->parentClassName = $parentClass->getShortName();
    }

    /**
     * Updates parent name for child
     *
     * @param string $newParentName New class name
     *
     * @return AbstractChildCreator
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
     * @return AbstractChildCreator
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
     * @return AbstractChildCreator
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
     * @param string|ReflectionClass|ParsedReflectionClass $interface
     */
    public function addInterface($interface)
    {
        $interfaceName = $interface;
        if ($interface instanceof ReflectionClass || $interface instanceof ParsedReflectionClass) {
            if (!$interface->isInterface()) {
                throw new \InvalidArgumentException("Interface expected to add");
            }
            $interfaceName = $interface->getName();
        }
        $this->interfaces[] = $interfaceName;
    }

    /**
     * Creates a property
     *
     * @param int $propFlags See ReflectionProperty modifiers
     * @param string $propName Name of the property
     * @param null|string $defaultText Default value, should be string text!
     *
     * @return AbstractChildCreator
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
    public function __toString()
    {
        ksort($this->methodsCode);
        ksort($this->propertiesCode);
        $prefix = join(' ', Reflection::getModifierNames($this->class->getModifiers()));
        $code = sprintf("%sclass %s extends %s%s\n{\n%s\n%s\n}",
            $prefix ? "$prefix " : '',
            $this->name,
            $this->parentClassName,
            $this->interfaces ? ' implements ' . join(', ', $this->interfaces) : '',
            $this->indent(join("\n", $this->propertiesCode)),
            $this->indent(join("\n", $this->methodsCode))
        );
        return $code;
    }

    /**
     * Creates a method code from Reflection
     *
     * @param ReflectionMethod|ParsedReflectionMethod $method Reflection for method
     * @param string $body Body of method
     * @param int $indent Spaces to indent
     *
     * @return string
     */
    protected function getOverriddenMethod($method, $body)
    {
        $code = sprintf("%s%s function %s%s(%s)\n{\n%s\n}\n",
            "/**\n * {@inheritdoc}\n */\n",
            join(' ', Reflection::getModifierNames($method->getModifiers())),
            $method->returnsReference() ? '&' : '',
            $method->getName(),
            join(', ', $this->getParameters($method->getParameters())),
            $this->indent($body)
        );
        return $code;
    }

    /**
     * Indent block of code
     *
     * @param string $text Non-indented text
     * @param integer $spaces Number of spaces for indentation
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
     * @param array|ReflectionParamter[]|ParsedReflectionParameter[] $parameters List of parameters
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
     * @param ReflectionParameter|ParsedReflectionParameter $parameter Reflection parameter
     *
     * @return string
     */
    protected function getParameterCode($parameter)
    {
        $type = '';
        if ($parameter->isArray()) {
            $type = 'array';
        } elseif ($parameter->getClass()) {
            $type = '\\' . $parameter->getClass()->getName();
        }
        $defaultValue = null;
        $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
        if ($isDefaultValueAvailable) {
            if ($parameter instanceof ParsedReflectionParameter) {
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
            $parameter->getName(),
            $isDefaultValueAvailable ? (" = " . $defaultValue) : ''
        );
        return $code;
    }
}
