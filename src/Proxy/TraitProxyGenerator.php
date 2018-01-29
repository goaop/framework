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

use Go\Aop\Advice;
use Go\Aop\Intercept\MethodInvocation;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use ReflectionClass;
use ReflectionMethod;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\TraitGenerator;
use Zend\Code\Generator\ValueGenerator;
use Zend\Code\Reflection\DocBlockReflection;

/**
 * Trait proxy builder that is used to generate a trait from the list of joinpoints
 */
class TraitProxyGenerator extends ClassProxyGenerator
{

    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $originalTrait        Original class reflection
     * @param string          $parentTraitName      Parent trait name to use
     * @param string[][]      $traitAdvices         List of advices for class
     * @param bool            $useParameterWidening Enables usage of parameter widening feature
     */
    public function __construct(
        ReflectionClass $originalTrait,
        string $parentTraitName,
        array $traitAdvices,
        bool $useParameterWidening
    ) {
        $this->advices        = $traitAdvices;
        $dynamicMethodAdvices = $traitAdvices[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices  = $traitAdvices[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
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
     * Returns a joinpoint for the specific trait
     *
     * @param string $className     Name of the class
     * @param string $joinPointType Type of joinpoint (static or dynamic method)
     * @param string $methodName    Name of the method
     * @param array  $advices       List of advices for this trait method
     *
     * @return MethodInvocation
     */
    public static function getJoinPoint(
        string $className,
        string $joinPointType,
        string $methodName,
        array $advices
    ): MethodInvocation {
        static $accessor;

        if ($accessor === null) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->get('aspect.advisor.accessor');
        }

        $filledAdvices = [];
        foreach ($advices as $advisorName) {
            $filledAdvices[] = $accessor->$advisorName;
        }

        $joinPoint = new self::$invocationClassMap[$joinPointType]($className, $methodName . '➩', $filledAdvices);

        return $joinPoint;
    }

    /**
     * Creates definition for trait method body
     *
     * @param ReflectionMethod $method Method reflection
     *
     * @return string new method body
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $class    = '\\' . __CLASS__;
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $argumentList = new FunctionCallArgumentListGenerator($method);
        $argumentCode = $argumentList->generate();
        $argumentCode = $scope . ($argumentCode ? ", $argumentCode" : '');

        $return = 'return ';
        if (PHP_VERSION_ID >= 70100 && $method->hasReturnType()) {
            $returnType = (string) $method->getReturnType();
            if ($returnType === 'void') {
                // void return types should not return anything
                $return = '';
            }
        }

        $advicesArray = new ValueGenerator($this->advices[$prefix][$method->name], ValueGenerator::TYPE_ARRAY_SHORT);
        $advicesArray->setArrayDepth(1);
        $advicesCode = $advicesArray->generate();

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
