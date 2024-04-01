<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy\Part;

use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\ValueGenerator;
use ReflectionFunctionAbstract;
use ReflectionNamedType;

/**
 * Generates parameters from reflection definition
 */
final class FunctionParameterList
{
    /**
     * @var ParameterGenerator[]
     */
    private array $generatedParameters = [];

    /**
     * ParameterListGenerator constructor.
     *
     * @param ReflectionFunctionAbstract $functionLike    Instance of function or method
     * @param bool                       $useTypeWidening Should generated parameters use type widening
     */
    public function __construct(ReflectionFunctionAbstract $functionLike, bool $useTypeWidening = false)
    {
        $reflectionParameters = $functionLike->getParameters();
        foreach ($reflectionParameters as $reflectionParameter) {
            $defaultValue = null;

            $parameterTypeName = null;
            if (!$useTypeWidening && $reflectionParameter->hasType()) {
                $parameterReflectionType = $reflectionParameter->getType();
                if ($parameterReflectionType instanceof ReflectionNamedType) {
                    $parameterTypeName = $parameterReflectionType->getName();
                } else {
                    $parameterTypeName = (string) $parameterReflectionType;
                }
            }

            $generatedParameter = new ParameterGenerator(
                $reflectionParameter->getName(),
                $parameterTypeName,
                $defaultValue,
                $reflectionParameter->getPosition(),
                $reflectionParameter->isPassedByReference()
            );
            $generatedParameter->setVariadic($reflectionParameter->isVariadic());

            if (!$reflectionParameter->isVariadic()) {
                $isDefaultValueAvailable = $reflectionParameter->isDefaultValueAvailable();
                if ($isDefaultValueAvailable) {
                    $defaultValue = new ValueGenerator($reflectionParameter->getDefaultValue());
                } elseif ($reflectionParameter->isOptional()) {
                    $defaultValue = new ValueGenerator(null);
                }

                if ($defaultValue instanceof ValueGenerator) {
                    $generatedParameter->setDefaultValue($defaultValue);
                }
            }

            $this->generatedParameters[] = $generatedParameter;
        }
    }

    /**
     * Returns the list of generated parameters
     *
     * @return ParameterGenerator[]
     */
    public function getGeneratedParameters(): array
    {
        return $this->generatedParameters;
    }
}
