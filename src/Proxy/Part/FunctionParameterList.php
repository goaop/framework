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

use ReflectionFunctionAbstract;
use Laminas\Code\Generator\ParameterGenerator;
use Laminas\Code\Generator\ValueGenerator;

/**
 * Generates parameters from reflection definition
 */
final class FunctionParameterList
{
    /**
     * @var ParameterGenerator[]
     */
    private $generatedParameters = [];

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

            $isDefaultValueAvailable = $reflectionParameter->isDefaultValueAvailable();
            if ($isDefaultValueAvailable) {
                $defaultValue = new ValueGenerator($reflectionParameter->getDefaultValue());
            } elseif ($reflectionParameter->isOptional() && !$reflectionParameter->isVariadic()) {
                $defaultValue = new ValueGenerator(null);
            }

            $generatedParameter = new ParameterGenerator(
                $reflectionParameter->getName(),
                $useTypeWidening ? '' : $reflectionParameter->getType(),
                $defaultValue,
                $reflectionParameter->getPosition(),
                $reflectionParameter->isPassedByReference()
            );
            $generatedParameter->setVariadic($reflectionParameter->isVariadic());

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
