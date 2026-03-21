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

use Go\Aop\Intercept\MethodInvocation;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\Proxy\Part\TraitMethodInvocationCallASTGenerator;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Generator\TraitGenerator;
use Laminas\Code\Reflection\DocBlockReflection;
use PhpParser\PrettyPrinter\Standard;
use ReflectionClass;
use ReflectionMethod;

/**
 * Trait proxy builder that is used to generate a trait from the list of joinpoints
 */
class TraitProxyGenerator extends ClassProxyGenerator
{
    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $originalTrait    Original class reflection
     * @param string          $parentTraitName  Parent trait name to use
     * @param string[][]      $traitAdviceNames List of advices for class
     */
    public function __construct(
        ReflectionClass $originalTrait,
        string $parentTraitName,
        array $traitAdviceNames,
        bool $useParameterWidening
    ) {
        $this->adviceNames          = $traitAdviceNames;
        $this->useParameterWidening = $useParameterWidening;

        $dynamicMethodAdvices = $traitAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices  = $traitAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $interceptedMethods   = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $generatedMethods     = $this->interceptMethods($originalTrait, $interceptedMethods);

        $this->generator = new TraitGenerator(
            $originalTrait->getShortName(),
            $originalTrait->getNamespaceName(),
            null,
            null,
            [],
            [],
            $generatedMethods,
            DocBlockGenerator::fromReflection(new DocBlockReflection($originalTrait->getDocComment()))
        );

        // Normalize FQDN
        $namespaceParts       = explode('\\', $parentTraitName);
        $parentNormalizedName = end($namespaceParts);
        $this->generator->addTrait($parentNormalizedName);

        foreach ($interceptedMethods as $methodName) {
            $fullName = $parentNormalizedName . '::' . $methodName;
            $this->generator->addTraitAlias($fullName, $methodName . '➩', ReflectionMethod::IS_PROTECTED);
        }
    }

    /**
     * Returns a method invocation for the specific trait method
     *
     * @param array $adviceNames List of advices for this trait method
     */
    public static function getJoinPoint(
        string $className,
        string $joinPointType,
        string $methodName,
        array $adviceNames
    ): MethodInvocation {
        static $accessor;

        if ($accessor === null) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $filledAdvices = [];
        foreach ($adviceNames as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        $joinPoint = new self::$invocationClassMap[$joinPointType]($filledAdvices, $className, $methodName . '➩');

        return $joinPoint;
    }

    /**
     * Creates string definition for trait method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $methodCall = new TraitMethodInvocationCallASTGenerator($method);
        $statements = $methodCall->generate($this->adviceNames[$prefix][$method->name]);
        $printer    = new Standard();

        return $printer->prettyPrint($statements);
    }

    /**
     * {@inheritDoc}
     */
    public function generate(): string
    {
        return $this->generator->generate();
    }
}
