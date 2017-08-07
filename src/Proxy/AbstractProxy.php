<?php
declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Reflection;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

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
    protected $advices = [];

    /**
     * PHP expression string for accessing LSB information
     *
     * @var string
     */
    protected static $staticLsbExpression = 'static::class';

    /**
     * Constructs an abstract proxy class
     *
     * @param array $advices List of advices
     */
    public function __construct(array $advices = [])
    {
        $this->advices = $this->flattenAdvices($advices);
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
        $lines = array_map(function($line) use ($pad) {
            return $pad . $line;
        }, explode("\n", $text));

        return join("\n", $lines);
    }

    /**
     * Returns list of string representation of parameters
     *
     * @param array|ReflectionParameter[] $parameters List of parameters
     *
     * @return array
     */
    protected function getParameters(array $parameters)
    {
        $parameterDefinitions = [];
        foreach ($parameters as $parameter) {
            $parameterDefinitions[] = $this->getParameterCode($parameter);
        }

        return $parameterDefinitions;
    }

    /**
     * Return string representation of parameter
     *
     * @param ReflectionParameter $parameter Reflection parameter
     *
     * @return string
     */
    protected function getParameterCode(ReflectionParameter $parameter)
    {
        $type = '';
        if (PHP_VERSION_ID >= 70000) {
            $reflectionType = $parameter->getType();
            if ($reflectionType) {
                $nullablePrefix = (PHP_VERSION_ID >= 70100 && $reflectionType->allowsNull()) ? '?' : '';
                $nsPrefix       = $reflectionType->isBuiltin() ? '' : '\\';
                $type           = $nullablePrefix . $nsPrefix . (string) $reflectionType;
            }
        } else {
            if ($parameter->isArray()) {
                $type = 'array';
            } elseif ($parameter->isCallable()) {
                $type = 'callable';
            } elseif ($parameter->getClass()) {
                $type = '\\' . $parameter->getClass()->name;
            }
        }
        $defaultValue = null;
        $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
        if ($isDefaultValueAvailable) {
            $defaultValue = var_export($parameter->getDefaultValue(), true);
        } elseif ($parameter->isOptional() && !$parameter->isVariadic()) {
            $defaultValue = 'null';
        }
        $code = (
            ($type ? "$type " : '') . // Typehint
            ($parameter->isPassedByReference() ? '&' : '') . // By reference sign
            ($parameter->isVariadic() ? '...' : '') . // Variadic symbol
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
        $flattenAdvices = [];
        foreach ($advices as $type => $typedAdvices) {
            foreach ($typedAdvices as $name => $concreteAdvices) {
                if (is_array($concreteAdvices)) {
                    $flattenAdvices[$type][$name] = array_keys($concreteAdvices);
                }
            }
        }

        return $flattenAdvices;
    }

    /**
     * Prepares a line with args from the method definition
     *
     * @param ReflectionFunctionAbstract $functionLike
     *
     * @return string
     */
    protected function prepareArgsLine(ReflectionFunctionAbstract $functionLike)
    {
        $argumentsPart = [];
        $arguments     = [];
        $hasOptionals  = false;

        foreach ($functionLike->getParameters() as $parameter) {
            $byReference  = ($parameter->isPassedByReference() && !$parameter->isVariadic()) ? '&' : '';
            $hasOptionals = $hasOptionals || $parameter->isOptional();

            $arguments[] = $byReference . '$' . $parameter->name;
        }

        $isVariadic = $functionLike->isVariadic();
        if ($isVariadic) {
            $argumentsPart[] = array_pop($arguments);
        }
        if (!empty($arguments)) {
            // Unshifting to keep correct order
            $argumentLine = '[' . join(', ', $arguments) . ']';
            if ($hasOptionals) {
                $argumentLine = "\\array_slice($argumentLine, 0, \\func_num_args())";
            }
            array_unshift($argumentsPart, $argumentLine);
        }

        return join(', ', $argumentsPart);
    }

    /**
     * Creates a function code from Reflection
     *
     * @param ReflectionFunctionAbstract $functionLike Reflection for method
     * @param string $body Body of method
     *
     * @return string
     */
    protected function getOverriddenFunction(ReflectionFunctionAbstract $functionLike, $body)
    {
        $reflectionReturnType = PHP_VERSION_ID >= 70000 ? $functionLike->getReturnType() : '';
        $modifiersLine        = '';
        if ($reflectionReturnType) {
            $nullablePrefix = $reflectionReturnType->allowsNull() ? '?' : '';
            $nsPrefix       = $reflectionReturnType->isBuiltin() ? '' : '\\';

            $reflectionReturnType = $nullablePrefix . $nsPrefix . (string) $reflectionReturnType;
        }
        if ($functionLike instanceof ReflectionMethod) {
            $modifiersLine = join(' ', Reflection::getModifierNames($functionLike->getModifiers()));
        }

        $code = (
            preg_replace('/ {4}|\t/', '', $functionLike->getDocComment()) . "\n" . // Original Doc-block
            $modifiersLine . // List of modifiers (for methods)
            ' function ' . // 'function' keyword
            ($functionLike->returnsReference() ? '&' : '') . // By reference symbol
            $functionLike->name . // Name of the function
            '(' . // Start of parameters list
            join(', ', $this->getParameters($functionLike->getParameters())) . // List of parameters
            ")" . // End of parameters list
            ($reflectionReturnType ? " : $reflectionReturnType" : '') . // Return type, if present
            "\n" .
            "{\n" . // Start of method body
            $this->indent($body) . "\n" . // Method body
            "}\n" // End of method body
        );

        return $code;
    }
}
