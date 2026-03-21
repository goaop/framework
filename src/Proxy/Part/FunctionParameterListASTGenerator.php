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

use Go\ParserReflection\ReflectionAttribute;
use PhpParser\BuilderFactory;
use ReflectionFunctionAbstract;

/**
 * Generates parameters from reflection definition
 */
final readonly class FunctionParameterListASTGenerator
{
    /**
     * ParameterListGenerator constructor.
     *
     * @param ReflectionFunctionAbstract $functionLike    Instance of function or method
     * @param bool                       $useTypeWidening Should generated parameters use type widening
     */
    public function __construct(
        private ReflectionFunctionAbstract $functionLike,
        private bool                       $useTypeWidening = false
    ) {}

    public function generate(): array
    {
        $generatedParameters = [];

        $builder      = new BuilderFactory();
        $functionLike = $this->functionLike;

        $reflectionParameters = $functionLike->getParameters();
        foreach ($reflectionParameters as $reflectionParameter) {
            $parameterBuilder = $builder->param($reflectionParameter->getName());

            if (!$this->useTypeWidening && $reflectionParameter->hasType()) {
                $parameterReflectionType = $reflectionParameter->getType();
                $parameterBuilder->setType((string) $parameterReflectionType);
            }

            foreach ($reflectionParameter->getAttributes() as $attribute) {
                if ($attribute instanceof ReflectionAttribute) {
                    // This will generate attribute in the exact way it was defined in the original code
                    $parameterBuilder->addAttribute($attribute->getNode());
                } else {
                    // Otherwise we try to do our best with attribute name and arguments pair
                    $parameterBuilder->addAttribute(
                        $builder->attribute(
                            '\\' . $attribute->getName(),
                            $attribute->getArguments()
                        )
                    );
                }
            }

            if ($reflectionParameter->isPassedByReference()) {
                $parameterBuilder->makeByRef();
            }

            if ($reflectionParameter->isVariadic()) {
                $parameterBuilder->makeVariadic();
            }

            // For parameters, we don't use any from following modifiers: private, protected, public, readonly
            if ($reflectionParameter->isDefaultValueAvailable()) {
                $parameterBuilder->setDefault($reflectionParameter->getDefaultValue());
            } elseif ($reflectionParameter->isOptional()) {
                $parameterBuilder->setDefault(null);
            }

            $generatedParameters[] = $parameterBuilder->getNode();
        }

        return $generatedParameters;
    }
}
