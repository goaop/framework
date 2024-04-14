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
use Go\Aop\Framework\ClassFieldAccess;
use Go\Aop\Framework\DynamicClosureMethodInvocation;
use Go\Aop\Framework\ReflectionConstructorInvocation;
use Go\Aop\Framework\StaticClosureMethodInvocation;
use Go\Aop\Framework\StaticInitializationJoinpoint;
use Go\Aop\Intercept\Joinpoint;
use Go\Aop\Proxy;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\LazyAdvisorAccessor;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedConstructorGenerator;
use Go\Proxy\Part\InterceptedMethodGenerator;
use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Proxy\Part\PropertyInterceptionTrait;
use Laminas\Code\Generator\ClassGenerator;
use Laminas\Code\Generator\DocBlockGenerator;
use Laminas\Code\Reflection\DocBlockReflection;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use UnexpectedValueException;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyGenerator
{
    /**
     * Static mappings for class name for excluding if..else check
     */
    protected static array $invocationClassMap = [
        AspectContainer::METHOD_PREFIX        => DynamicClosureMethodInvocation::class,
        AspectContainer::STATIC_METHOD_PREFIX => StaticClosureMethodInvocation::class,
        AspectContainer::PROPERTY_PREFIX      => ClassFieldAccess::class,
        AspectContainer::STATIC_INIT_PREFIX   => StaticInitializationJoinpoint::class,
        AspectContainer::INIT_PREFIX          => ReflectionConstructorInvocation::class
    ];

    /**
     * List of advices that are used for generation of child
     */
    protected array $adviceNames = [];

    /**
     * Instance of class generator
     */
    protected ClassGenerator $generator;

    /**
     * Should parameter widening be used or not
     */
    protected bool $useParameterWidening;

    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass $originalClass        Original class reflection
     * @param string          $parentClassName      Parent class name to use
     * @param string[][]      $classAdviceNames     List of advices for class
     * @param bool            $useParameterWidening Enables usage of parameter widening feature
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $parentClassName,
        array $classAdviceNames,
        bool $useParameterWidening
    ) {
        $this->adviceNames          = $classAdviceNames;
        $this->useParameterWidening = $useParameterWidening;

        $dynamicMethodAdvices  = $classAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices   = $classAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $propertyAdvices       = $classAdviceNames[AspectContainer::PROPERTY_PREFIX] ?? [];
        $interceptedMethods    = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $interceptedProperties = array_keys($propertyAdvices);
        $introducedInterfaces  = $classAdviceNames[AspectContainer::INTRODUCTION_INTERFACE_PREFIX]['root'] ?? [];
        $introducedTraits      = $classAdviceNames[AspectContainer::INTRODUCTION_TRAIT_PREFIX]['root'] ?? [];

        $generatedProperties = [new JoinPointPropertyGenerator($classAdviceNames)];
        $generatedMethods    = $this->interceptMethods($originalClass, $interceptedMethods);

        $introducedInterfaces[] = '\\' . Proxy::class;

        if (!empty($interceptedProperties)) {
            $generatedMethods['__construct'] = new InterceptedConstructorGenerator(
                $interceptedProperties,
                $originalClass->getConstructor(),
                $generatedMethods['__construct'] ?? null,
                $useParameterWidening
            );
            $introducedTraits[] = '\\' . PropertyInterceptionTrait::class;
        }

        $this->generator = new ClassGenerator(
            $originalClass->getShortName(),
            !empty($originalClass->getNamespaceName()) ? $originalClass->getNamespaceName() : null,
            $originalClass->isFinal() ? ClassGenerator::FLAG_FINAL : null,
            $parentClassName,
            $introducedInterfaces,
            $generatedProperties,
            $generatedMethods
        );
        if ($originalClass->getDocComment()) {
            $reflectionDocBlock = new DocBlockReflection($originalClass->getDocComment());
            $this->generator->setDocBlock(DocBlockGenerator::fromReflection($reflectionDocBlock));
        }

        $this->generator->addTraits($introducedTraits);
    }

    /**
     * Adds use alias for this class
     */
    public function addUse(string $use, string $useAlias = null): void
    {
        $this->generator->addUse($use, $useAlias);
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     */
    public static function injectJoinPoints(string $targetClassName): void
    {
        $reflectionClass    = new ReflectionClass($targetClassName);
        $joinPointsProperty = $reflectionClass->getProperty(JoinPointPropertyGenerator::NAME);

        $advices    = $joinPointsProperty->getValue();
        $joinPoints = static::wrapWithJoinPoints($advices, $reflectionClass->name);
        $joinPointsProperty->setValue(null, $joinPoints);

        $staticInit = AspectContainer::STATIC_INIT_PREFIX . ':root';
        if (isset($joinPoints[$staticInit])) {
            ($joinPoints[$staticInit])();
        }
    }

    /**
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $classCode = $this->generator->generate();

        return $classCode
            // Inject advices on call
            . '\\' . self::class . '::injectJoinPoints(' . $this->generator->getName() . '::class);';
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param array|Advice[][][] $classAdvices Advices for specific class
     *
     * @throws UnexpectedValueException If joinPoint type is unknown
     *
     * NB: Extension should be responsible for wrapping advice with join point.
     *
     * @return Joinpoint[] returns list of joinpoint ready to use
     */
    protected static function wrapWithJoinPoints(array $classAdvices, string $className): array
    {
        static $accessor = null;

        if (!isset($accessor)) {
            $aspectKernel = AspectKernel::getInstance();
            $accessor     = $aspectKernel->getContainer()->getService(LazyAdvisorAccessor::class);
        }

        $joinPoints = [];

        foreach ($classAdvices as $joinPointType => $typedAdvices) {
            // if not isset then we don't want to create such invocation for class
            if (!isset(self::$invocationClassMap[$joinPointType])) {
                continue;
            }
            foreach ($typedAdvices as $joinPointName => $advices) {
                $filledAdvices = [];
                foreach ($advices as $advisorName) {
                    $filledAdvices[] = $accessor->$advisorName;
                }

                $joinpoint = new self::$invocationClassMap[$joinPointType]($filledAdvices, $className, $joinPointName);
                $joinPoints["$joinPointType:$joinPointName"] = $joinpoint;
            }
        }

        return $joinPoints;
    }

    /**
     * Returns list of intercepted method generators for class by method names
     *
     * @param string[] $methodNames List of methods to intercept
     *
     * @return InterceptedMethodGenerator[]
     */
    protected function interceptMethods(ReflectionClass $originalClass, array $methodNames): array
    {
        $interceptedMethods = [];
        foreach ($methodNames as $methodName) {
            $reflectionMethod = $originalClass->getMethod($methodName);
            $methodBody       = $this->getJoinpointInvocationBody($reflectionMethod);

            $interceptedMethods[$methodName] = new InterceptedMethodGenerator(
                $reflectionMethod,
                $methodBody,
                $this->useParameterWidening
            );
        }

        return $interceptedMethods;
    }

    /**
     * Creates string definition for method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;

        $argumentList = new FunctionCallArgumentListGenerator($method);
        $argumentCode = $argumentList->generate();
        $return       = 'return ';
        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['void', 'never'], true)) {
                // void/never return types should not return anything
                $return = '';
            }
        }

        if (!empty($argumentCode)) {
            $scope = "$scope, $argumentCode";
        }

        $body = "{$return}self::\$__joinPoints['{$prefix}:{$method->name}']->__invoke($scope);";

        return $body;
    }
}
