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

use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\MethodGenerator;
use Laminas\Code\Reflection\DocBlockReflection;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Prepares the definition of intercepted method
 */
final class InterceptedMethodGenerator extends MethodGenerator
{
    /**
     * InterceptedMethod constructor.
     *
     * @param ReflectionMethod $reflectionMethod Instance of original method
     * @param string           $body             Method body
     * @param bool             $useTypeWidening  Should generator use parameter widening for PHP>=7.2
     */
    public function __construct(ReflectionMethod $reflectionMethod, string $body, bool $useTypeWidening = false)
    {
        parent::__construct($reflectionMethod->getName());

        $declaringClass = $reflectionMethod->getDeclaringClass();

        if ($reflectionMethod->hasReturnType()) {
            $reflectionReturnType = $reflectionMethod->getReturnType();
            if ($reflectionReturnType instanceof ReflectionNamedType) {
                $returnTypeName = ($reflectionReturnType->allowsNull() ? '?' : '') . $reflectionReturnType->getName();
            } else {
                $returnTypeName = (string)$reflectionReturnType;
            }
            $this->setReturnType($returnTypeName);
        }

        if ($reflectionMethod->getDocComment()) {
            $reflectionDocBlock = new DocBlockReflection($reflectionMethod->getDocComment());
            $this->setDocBlock(DocBlockGenerator::fromReflection($reflectionDocBlock));
        }

        $this->setFinal($reflectionMethod->isFinal());

        if ($reflectionMethod->isPrivate()) {
            $this->setVisibility(self::VISIBILITY_PRIVATE);
        } elseif ($reflectionMethod->isProtected()) {
            $this->setVisibility(self::VISIBILITY_PROTECTED);
        } else {
            $this->setVisibility(self::VISIBILITY_PUBLIC);
        }

        $this->setInterface($declaringClass->isInterface());
        $this->setStatic($reflectionMethod->isStatic());
        $this->setReturnsReference($reflectionMethod->returnsReference());
        $this->setName($reflectionMethod->getName());

        $parameterList = new FunctionParameterList($reflectionMethod, $useTypeWidening);
        $this->setParameters($parameterList->getGeneratedParameters());
        $this->setBody($body);
    }
}
