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
use Go\Core\AspectContainer;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\TraitGenerator;
use Go\Proxy\Generator\TypeGenerator;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\TraitInterceptedPropertyGenerator;
use PhpParser\Node\Stmt\ClassMethod;
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
        $generatedProperties  = [];
        foreach ($traitAdviceNames[AspectContainer::PROPERTY_PREFIX] ?? [] as $propertyName => $adviceNames) {
            $property = $originalTrait->getProperty($propertyName);
            $normalizedAdviceNames = array_is_list($adviceNames) ? $adviceNames : array_keys($adviceNames);
            $generatedProperties[] = (new TraitInterceptedPropertyGenerator($property, $normalizedAdviceNames))->getNode();
        }

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
            $docBlock,
            $generatedProperties
        );

        // Use the short (unqualified) trait name only when the parent trait and proxy
        // trait share the same namespace; otherwise keep the FQCN
        $lastBackslash     = strrpos($parentTraitName, '\\');
        $traitNamespace    = $lastBackslash !== false ? substr($parentTraitName, 0, $lastBackslash) : '';
        $sameNamespace     = $traitNamespace === $originalTrait->getNamespaceName();
        $parentNormalizedName = ($sameNamespace && $lastBackslash !== false) ? substr($parentTraitName, $lastBackslash + 1) : $parentTraitName;
        $traitGenerator->addTrait($parentNormalizedName);

        foreach ($interceptedMethods as $methodName) {
            $fullName = $parentNormalizedName . '::' . $methodName;
            $traitGenerator->addTraitAlias($fullName, AbstractMethodInvocation::TRAIT_ALIAS_PREFIX . $methodName, ReflectionMethod::IS_PRIVATE);
        }

        // Register use-imports for AOP classes referenced in generated method bodies.
        // Determine needed invocation types from actual method signatures, not advice
        // category keys, because callers may place static-method advices under METHOD_PREFIX.
        $traitGenerator->addUse('Go\Aop\Framework\InterceptorInjector');
        foreach ($interceptedMethods as $methodName) {
            if ($originalTrait->hasMethod($methodName) && $originalTrait->getMethod($methodName)->isStatic()) {
                $traitGenerator->addUse('Go\Aop\Intercept\StaticMethodInvocation');
            } else {
                $traitGenerator->addUse('Go\Aop\Intercept\DynamicMethodInvocation');
            }
        }
        $propertyAdvices = $traitAdviceNames[AspectContainer::PROPERTY_PREFIX] ?? [];
        if (!empty($propertyAdvices)) {
            $traitGenerator->addUse('Go\Aop\Intercept\FieldAccess');
            $traitGenerator->addUse('Go\Aop\Intercept\FieldAccessType');
        }

        // Store generator instance for compatibility with parent generate() call
        $this->generator = $traitGenerator;
    }

    /**
     * Creates string definition for trait method body by method reflection
     *
     * In a trait proxy, all intercepted methods always have a private __aop__ alias in the
     * trait-use block (from the parent trait). So the callable always references the alias.
     */
    protected function getJoinpointInvocationBody(ReflectionMethod $method, ?ReflectionClass $originalClass = null): string
    {
        $isStatic = $method->isStatic();
        $scope    = $isStatic ? 'static::class' : '$this';
        $prefix   = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
        $injectorMethod = $isStatic ? 'forStaticMethod' : 'forMethod';

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

        $adviceNames = $this->adviceNames[$prefix][$method->name]
            ?? ($isStatic ? ($this->adviceNames[AspectContainer::METHOD_PREFIX][$method->name] ?? []) : []);
        $advicesArrayValue = new ValueGenerator($adviceNames);
        $advicesArrayValue->setArrayDepth(1);
        $advicesCode = $advicesArrayValue->generate();
        $returnTypeString = $method->hasReturnType() ? ', ' . TypeGenerator::renderTypeForPhpDoc($method->getReturnType()) : '';
        // On PHP 8.5+, ReflectionNamedType::getName() resolves 'self'/'parent' to the actual FQCN.
        // Use the raw AST return-type node when available (goaop/parser-reflection) to preserve keywords.
        if ($method->hasReturnType() && method_exists($method, 'getNode')) {
            $node = $method->getNode();
            if ($node instanceof ClassMethod) {
                $astReturnType = $node->getReturnType();
                $returnTypeString = $astReturnType !== null ? ', ' . TypeGenerator::renderAstTypeForPhpDoc($astReturnType) : '';
            }
        }
        $joinPointType = $isStatic
            ? 'StaticMethodInvocation<self' . $returnTypeString . '>'
            : 'DynamicMethodInvocation<self' . $returnTypeString . '>';

        // All intercepted methods in a trait proxy have __aop__ aliases from the parent trait.
        $callableExpression = $isStatic
            ? 'self::' . AbstractMethodInvocation::TRAIT_ALIAS_PREFIX . $method->name . '(...)'
            : '$this->' . AbstractMethodInvocation::TRAIT_ALIAS_PREFIX . $method->name . '(...)';

        return <<<BODY
        /** @var {$joinPointType} \$__joinPoint */
        static \$__joinPoint = InterceptorInjector::{$injectorMethod}(self::class, '{$method->name}', {$advicesCode}, {$callableExpression});
        {$return}\$__joinPoint->__invoke($argumentCode);
        BODY;
    }

    /**
     * {@inheritDoc}
     */
    public function addUse(string $use, ?string $useAlias = null): void
    {
        if ($use !== '' && $this->generator instanceof TraitGenerator) {
            $this->generator->addUse($use, $useAlias !== '' ? $useAlias : null);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function generate(): string
    {
        return $this->generator->generate();
    }
}
