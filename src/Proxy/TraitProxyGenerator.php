<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2012, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Aop\Intercept\Joinpoint;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\TraitGenerator;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Trait proxy builder that is used to generate a trait from the list of joinpoints
 */
class TraitProxyGenerator extends ClassProxyGenerator
{
    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass<object> $originalTrait    Original class reflection
     * @param string                  $parentTraitName  Parent trait name to use
     * @param string[][][]            $traitAdviceNames List of advices for class
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

        $docComment = $originalTrait->getDocComment();
        $docBlock   = $docComment !== false ? DocBlockGenerator::fromDocComment($docComment) : null;

        $methodGenerators = array_map(
            static fn($m) => $m->getGenerator(),
            array_values($generatedMethods)
        );

        $traitGenerator = new TraitGenerator(
            $originalTrait->getShortName(),
            $originalTrait->getNamespaceName(),
            $methodGenerators,
            $docBlock
        );

        // Normalize FQDN for the parent trait reference
        $namespaceParts       = explode('\\', $parentTraitName);
        $parentNormalizedName = end($namespaceParts);
        $traitGenerator->addTrait($parentNormalizedName);

        foreach ($interceptedMethods as $methodName) {
            $fullName = $parentNormalizedName . '::' . $methodName;
            $traitGenerator->addTraitAlias($fullName, $methodName . '➩', ReflectionMethod::IS_PROTECTED);
        }

        // Store generator instance for compatibility with parent generate() call
        $this->generator = $traitGenerator;
    }

    /**
     * Returns a method invocation for the specific trait method
     *
     * @param string[] $adviceNames List of advices for this trait method
     */
    public static function getJoinPoint(
        string $className,
        string $joinPointType,
        string $methodName,
        array $adviceNames
    ): Joinpoint {
        static $accessor;

        if ($accessor === null) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $filledAdvices = [];
        foreach ($adviceNames as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        $joinPoint = new self::$invocationClassMap[$joinPointType]($className, $methodName . '➩', $filledAdvices);

        return $joinPoint;
    }

    /**
     * Creates string definition for trait method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $class    = '\\' . self::class;
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $argumentList = new FunctionCallArgumentListGenerator($method);
        $argumentCode = $argumentList->generate();
        $argumentCode = $scope . ($argumentCode !== '' ? ", $argumentCode" : '');

        $return = 'return ';
        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
                // void/never return types should not return anything
                $return = '';
            }
        }

        $advicesArrayValue = new ValueGenerator(
            $this->adviceNames[$prefix][$method->name]
        );
        $advicesArrayValue->setArrayDepth(1);
        $advicesCode = $advicesArrayValue->generate();

        return <<<BODY
static \$__joinPoint;
if (\$__joinPoint === null) {
    \$__joinPoint = {$class}::getJoinPoint(__CLASS__, '{$prefix}', '{$method->name}', {$advicesCode});
}
{$return}\$__joinPoint->__invoke($argumentCode);
BODY;
    }

    /**
     * {@inheritDoc}
     */
    public function generate(): string
    {
        return $this->generator->generate();
    }
}
