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

use Go\Proxy\Generator\ParameterGenerator;
use ReflectionFunctionAbstract;

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
        foreach ($functionLike->getParameters() as $reflectionParameter) {
            $this->generatedParameters[] = ParameterGenerator::fromReflection($reflectionParameter, $useTypeWidening);
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
