<?php
/**
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use ReflectionParameter as Parameter;
use TokenReflection\ReflectionParameter as ParsedParameter;

/**
 * Abstract class for building different proxies
 */
abstract class AbstractProxy
{

    /**
     * Indent for source code
     *
     * @var int
     */
    protected $indent = 4;

    /**
     * List of advices that are used for generation of child
     *
     * @var array
     */
    protected $advices = array();

    /**
     * PHP expression string for accessing LSB information
     *
     * @var string
     */
    protected $staticLsbExpression = '\get_called_class()';

    /**
     * Constructs an abstract proxy class
     *
     * @param array $advices List of advices
     * @param bool $useStaticForLsb Should proxy use 'static::class' instead of '\get_called_class()'
     */
    public function __construct(array $advices = array(), $useStaticForLsb = false)
    {
        $this->advices = $this->flattenAdvices($advices);
        if ($useStaticForLsb) {
            $this->staticLsbExpression = 'static::class';
        }
    }

    /**
     * Returns text representation of class
     *
     * @return string
     */
    abstract public function __toString();

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
            if ($parameter->name == '...') {
                continue;
            }
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
        } elseif ($parameter->isOptional()) {
            $defaultValue = 'null';
        }
        $code = (
            ($type ? "$type " : '') . // Typehint
            ($parameter->isPassedByReference() ? '&' : '') . // By reference sign
            '$' . // Variable symbol
            ($parameter->name) . // Name of the argument
            ($defaultValue !== null ? (" = " . $defaultValue) : '') // Default value if present
        );

        return $code;
    }

    /**
     * Replace concrete advices with list of ids
     *
     * @param $advices
     *
     * @return array flatten list of advices
     */
    private function flattenAdvices($advices)
    {
        $flattenAdvices = array();
        foreach ($advices as $type => $typedAdvices) {
            foreach ($typedAdvices as $name => $concreteAdvices) {
                if (is_array($concreteAdvices)) {
                    $flattenAdvices[$type][$name] = array_keys($concreteAdvices);
                }
            }
        }

        return $flattenAdvices;
    }
}
