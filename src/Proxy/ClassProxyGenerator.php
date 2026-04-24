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

use Go\Aop\Framework\AbstractMethodInvocation;
use Go\Aop\InitializationAware;
use Go\Aop\Proxy;
use Go\Aop\StaticInitializationAware;
use Go\Core\AspectContainer;
use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\ClassGenerator;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\GeneratorInterface;
use Go\Proxy\Generator\MethodGenerator;
use Go\Proxy\Generator\ParameterGenerator;
use Go\Proxy\Generator\TypeGenerator;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedMethodGenerator;
use Go\Proxy\Part\InterceptedPropertyGenerator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyGenerator
{
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
     * Generates a proxy class that wraps the original class body (now a trait) via trait-use.
     *
     * The original class has been converted to a trait named $traitName by WeavingTransformer.
     * The proxy class re-exposes the same name, parent, and interfaces as the original, uses
     * that trait, and aliases each intercepted method as `private __aop__<method>` so the
     * overriding method body can delegate to the original via a Closure::bind proceed closure.
     *
     * @param ReflectionClass<object> $originalClass        Original class reflection (before transformation)
     * @param string                  $traitName            FQCN of the generated trait (e.g. Ns\Foo__AopProxied)
     * @param string[][][]            $classAdviceNames     List of advices for class
     * @param bool                    $useParameterWidening Enables usage of parameter widening feature
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $traitName,
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

        $staticInitializationAdvices = array_values($classAdviceNames[AspectContainer::STATIC_INIT_PREFIX]['root'] ?? []);
        $initializationAdvices       = array_values($classAdviceNames[AspectContainer::INIT_PREFIX]['root'] ?? []);

        $generatedProperties = [];
        $generatedMethods    = $this->interceptMethods($originalClass, $interceptedMethods);
        foreach ($this->interceptProperties($originalClass, $propertyAdvices, $interceptedProperties) as $interceptedProperty) {
            $generatedProperties[] = $interceptedProperty;
        }

        // Proxy implements the same interfaces as the original class (no longer inherited)
        $originalInterfaces    = array_map(static fn(string $i) => '\\' . $i, $originalClass->getInterfaceNames());
        $introducedInterfaces  = array_merge($originalInterfaces, $introducedInterfaces);
        $introducedInterfaces[] = '\\' . Proxy::class;
        $introducedInterfaces   = array_values(array_unique($introducedInterfaces));

        // Extract underlying MethodGenerator instances for ClassGenerator
        $methodGenerators = array_map(
            static fn($m) => $m->getGenerator(),
            array_values($generatedMethods)
        );
        foreach ([
            [$staticInitializationAdvices, StaticInitializationAware::class, $this->createStaticInitializationMethod(...)],
            [$initializationAdvices, InitializationAware::class, $this->createInitializationMethod(...)],
        ] as [$advisorNames, $interfaceName, $methodFactory]) {
            if ($advisorNames === []) {
                continue;
            }
            $introducedInterfaces[] = '\\' . $interfaceName;
            $methodGenerators[]     = $methodFactory($advisorNames);
        }
        $introducedInterfaces = array_values(array_unique($introducedInterfaces));

        // Proxy parent = original class parent (not the trait — there is no inheritance layer)
        $parentClass     = $originalClass->getParentClass();
        $parentClassName = $parentClass !== false ? $parentClass->getName() : null;

        // Proxy flags: preserve final/abstract/readonly from original class.
        $flags = 0;
        if ($originalClass->isFinal()) {
            $flags |= ClassGenerator::FLAG_FINAL;
        }
        if ($originalClass->isAbstract()) {
            $flags |= ClassGenerator::FLAG_ABSTRACT;
        }
        if ($originalClass->isReadOnly()) {
            $flags |= ClassGenerator::FLAG_READONLY;
        }

        $classGenerator = new ClassGenerator(
            $originalClass->getShortName(),
            !empty($originalClass->getNamespaceName()) ? $originalClass->getNamespaceName() : null,
            $flags !== 0 ? $flags : null,
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

        // Always include the original class body trait — even when no methods are intercepted
        // (e.g. introduction-only aspects). addTraitAlias also registers the trait, so this
        // explicit addTraits call only matters when $interceptedMethods is empty.
        $classGenerator->addTraits([$traitName]);

        // Alias each intercepted method as private __aop__<name>
        foreach ($interceptedMethods as $methodName) {
            $reflectionMethod = $originalClass->getMethod($methodName);
            if ($reflectionMethod->class !== $originalClass->name) {
                continue;
            }

            $classGenerator->addTraitAlias($traitName, $methodName, AbstractMethodInvocation::TRAIT_ALIAS_PREFIX . $methodName, ReflectionMethod::IS_PRIVATE);
        }
        // Add any AOP-introduced traits
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
     * Generates the source code of child class
     */
    public function generate(): string
    {
        $classCode = $this->generator->generate();
        $staticInitializationAdvices = array_values($this->adviceNames[AspectContainer::STATIC_INIT_PREFIX]['root'] ?? []);

        if ($staticInitializationAdvices !== []) {
            $classCode .= "\n" . $this->generator->getName() . '::__aop__staticInitialization();';
        }

        return $classCode;
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
     * @param ReflectionClass<object> $originalClass
     * @param array<array-key, array<string>> $propertyAdvices
     * @param string[] $propertyNames Intercepted property names from advice map
     *
     * @return InterceptedPropertyGenerator[]
     */
    private function interceptProperties(ReflectionClass $originalClass, array $propertyAdvices, array $propertyNames): array
    {
        $interceptedProperties = [];
        if ($propertyNames === []) {
            return $interceptedProperties;
        }
        $targetProperties = array_fill_keys($propertyNames, true);
        $mask = ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE;
        foreach ($originalClass->getProperties($mask) as $property) {
            if (!isset($targetProperties[$property->getName()])) {
                continue;
            }
            $adviceNames = array_values($propertyAdvices[$property->getName()] ?? []);
            if ($adviceNames === []) {
                continue;
            }
            $interceptedProperties[] = new InterceptedPropertyGenerator($property, $adviceNames);
        }

        return $interceptedProperties;
    }

    /**
     * Creates string definition for method body by method reflection
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method): string
    {
        $isStatic = $method->isStatic();
        $invocationArguments = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
        $injectorMethod = $isStatic ? 'forStaticMethod' : 'forMethod';

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
            $invocationArguments .= ", $argumentCode";
        }

        $adviceNames = $this->adviceNames[$prefix][$method->name]
            ?? ($isStatic ? ($this->adviceNames[AspectContainer::METHOD_PREFIX][$method->name] ?? []) : []);
        $advicesArrayValue = new ValueGenerator($adviceNames);
        $advicesArrayValue->setArrayDepth(1);
        $advicesCode = $advicesArrayValue->generate();
        $returnTypeString   = $method->hasReturnType() ? ', ' . TypeGenerator::renderTypeForPhpDoc($method->getReturnType()) : '';
        $joinPointType = $isStatic
            ? '\\Go\\Aop\\Intercept\\StaticMethodInvocation<self' . $returnTypeString . '>|null'
            : '\\Go\\Aop\\Intercept\\DynamicMethodInvocation<self' . $returnTypeString . '>|null';

        $body = <<<BODY
        /** @var {$joinPointType} \$__joinPoint */
        static \$__joinPoint;
        if (\$__joinPoint === null) {
            \$__joinPoint = \\Go\\Aop\\Framework\\InterceptorInjector::{$injectorMethod}(self::class, '{$method->name}', {$advicesCode});
        }
        {$return}\$__joinPoint->__invoke($invocationArguments);
        BODY;

        return $body;
    }

    /**
     * @param non-empty-list<string> $advisorNames
     */
    private function createStaticInitializationMethod(array $advisorNames): MethodGenerator
    {
        $advicesValue = new ValueGenerator($advisorNames);
        $advicesValue->setArrayDepth(1);
        $advicesCode = $advicesValue->generate();

        $method = new MethodGenerator('__aop__staticInitialization');
        $method->setStatic(true);
        $method->setReturnType('void');
        $method->setBody(<<<BODY
        /** @var \\Go\\Aop\\Intercept\\ClassJoinpoint<self>|null \$__joinPoint */
        static \$__joinPoint;
        if (\$__joinPoint === null) {
            \$__joinPoint = \\Go\\Aop\\Framework\\InterceptorInjector::forStaticInitialization(self::class, {$advicesCode});
        }
        \$__joinPoint(static::class);
        BODY);

        return $method;
    }

    /**
     * @param non-empty-list<string> $advisorNames
     */
    private function createInitializationMethod(array $advisorNames): MethodGenerator
    {
        $advicesValue = new ValueGenerator($advisorNames);
        $advicesValue->setArrayDepth(1);
        $advicesCode = $advicesValue->generate();

        $method = new MethodGenerator('__aop__initialization');
        $method->setStatic(true);
        $method->setReturnType('static');
        $argumentsParameter = new ParameterGenerator(
            'arguments',
            TypeGenerator::fromTypeString('array'),
            false,
            false,
            new ValueGenerator([])
        );
        $method->addParameter($argumentsParameter);
        $method->setBody(<<<BODY
        /** @var \\Go\\Aop\\Intercept\\ConstructorInvocation<self>|null \$__joinPoint */
        static \$__joinPoint;
        if (\$__joinPoint === null) {
            \$__joinPoint = \\Go\\Aop\\Framework\\InterceptorInjector::forInitialization(self::class, {$advicesCode});
        }
        return \$__joinPoint->__invoke(\$arguments);
        BODY);

        return $method;
    }

}
