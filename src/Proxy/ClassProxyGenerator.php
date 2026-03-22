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
use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\ClassGenerator;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\GeneratorInterface;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedConstructorGenerator;
use Go\Proxy\Part\InterceptedMethodGenerator;
use Go\Proxy\Part\JoinPointPropertyGenerator;
use Go\Proxy\Part\PropertyInterceptionTrait;
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
     *
     * @var array<string, class-string<Joinpoint>>
     */
    protected static array $invocationClassMap = [
        // MethodInvocation subtypes — directly invoked via self::$__joinPoints[key]->__invoke() in generated method bodies
        AspectContainer::METHOD_PREFIX        => DynamicClosureMethodInvocation::class,
        AspectContainer::STATIC_METHOD_PREFIX => StaticClosureMethodInvocation::class,
        // Non-MethodInvocation types — accessed through explicit casts or instanceof checks, not from generated method bodies
        AspectContainer::PROPERTY_PREFIX      => ClassFieldAccess::class,              // cast in PropertyInterceptionTrait
        AspectContainer::STATIC_INIT_PREFIX   => StaticInitializationJoinpoint::class, // instanceof check in injectJoinPoints()
        AspectContainer::INIT_PREFIX          => ReflectionConstructorInvocation::class // accessed via ConstructorExecutionTransformer
    ];

    /**
     * List of advices that are used for generation of child
     *
     * @var string[][][]
     */
    protected array $adviceNames = [];

    /**
     * Instance of class generator (ClassGenerator or TraitGenerator via TraitProxyGenerator)
     */
    protected GeneratorInterface $generator;

    /**
     * Should parameter widening be used or not
     */
    protected bool $useParameterWidening;

    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass<object> $originalClass        Original class reflection
     * @param string                  $parentClassName      Parent class name to use
     * @param string[][][]            $classAdviceNames     List of advices for class
     * @param bool                    $useParameterWidening Enables usage of parameter widening feature
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

        $generatedProperties = [new JoinPointPropertyGenerator()];
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

        // Extract underlying MethodGenerator instances for ClassGenerator
        $methodGenerators = array_map(
            static fn($m) => $m->getGenerator(),
            array_values($generatedMethods)
        );

        $classGenerator = new ClassGenerator(
            $originalClass->getShortName(),
            !empty($originalClass->getNamespaceName()) ? $originalClass->getNamespaceName() : null,
            $originalClass->isFinal() ? ClassGenerator::FLAG_FINAL : null,
            $parentClassName,
            $introducedInterfaces,
            $generatedProperties,
            $methodGenerators
        );

        if ($originalClass->getDocComment()) {
            $classGenerator->setDocBlock(DocBlockGenerator::fromDocComment($originalClass->getDocComment()));
        }

        // Copy PHP 8+ attributes from original class to proxy so that runtime
        // attribute inspection on proxy objects returns the same attributes
        $classAttrGroups = AttributeGroupsGenerator::fromReflectionAttributes($originalClass->getAttributes());
        if (!empty($classAttrGroups)) {
            $classGenerator->addAttributeGroups($classAttrGroups);
        }

        $classGenerator->addTraits(array_values($introducedTraits));
        $this->generator = $classGenerator;
    }

    /**
     * Adds use alias for this class
     */
    public function addUse(string $use, ?string $useAlias = null): void
    {
        if ($use !== '' && $this->generator instanceof ClassGenerator) {
            $this->generator->addUse($use, $useAlias !== '' ? $useAlias : null);
        }
    }

    /**
     * Inject advices into given class
     *
     * NB This method will be used as a callback during source code evaluation to inject joinpoints
     *
     * @param string[][][] $advices List of advices to inject
     */
    public static function injectJoinPoints(string $targetClassName, array $advices = []): void
    {
        if (!class_exists($targetClassName)) {
            return;
        }
        $reflectionClass    = new ReflectionClass($targetClassName);
        $joinPointsProperty = $reflectionClass->getProperty(JoinPointPropertyGenerator::NAME);

        $joinPoints = static::wrapWithJoinPoints($advices, $reflectionClass->name);
        $joinPointsProperty->setValue(null, $joinPoints);

        // staticinit:root is a StaticInitializationJoinpoint, not a MethodInvocation.
        // It is invoked here immediately after class load, not from generated method bodies.
        $staticInit = AspectContainer::STATIC_INIT_PREFIX . ':root';
        if (isset($joinPoints[$staticInit]) && $joinPoints[$staticInit] instanceof StaticInitializationJoinpoint) {
            ($joinPoints[$staticInit])();
        }
    }

    /**
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $classCode    = $this->generator->generate();
        $advicesValue = new ValueGenerator($this->adviceNames);

        return $classCode
            // Inject advices on call
            . "\n" . '\\' . self::class . '::injectJoinPoints(' . $this->generator->getName() . '::class, ' . $advicesValue->generate() . ');';
    }

    /**
     * Wrap advices with joinpoint object
     *
     * @param string[][][] $classAdvices Advisor name strings indexed by join point type and name
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
     * @param ReflectionClass<object> $originalClass
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
