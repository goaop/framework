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
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\FieldAccess;
use Go\Core\AspectContainer;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\ProxyImports;
use Go\Proxy\Generator\TraitGenerator;
use Go\Proxy\Generator\Visibility;
use Go\Proxy\Part\TraitInterceptedPropertyGenerator;
use PhpParser\Node\Expr;
use ReflectionClass;
use ReflectionMethod;

/**
 * Trait proxy builder that is used to generate a trait from the list of joinpoints
 */
final class TraitProxyGenerator extends ClassProxyGenerator
{
    /**
     * Generates an child code by original class reflection and joinpoints for it
     *
     * @param ReflectionClass<covariant object> $originalTrait    Original class reflection
     * @param string                  $parentTraitName  Parent trait name to use
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $traitAdviceNames List of advices for class
     * @param array<string, string|null> $originalImports Imports of the original file: class name => alias
     * @param array<string, ReflectionMethod>|null $originalMethods Methods of the original trait by name, see
     *                                                              {@see self::indexMethods()}; indexed here when null
     */
    public function __construct(
        ReflectionClass $originalTrait,
        string $parentTraitName,
        array $traitAdviceNames,
        array $originalImports = [],
        ?array $originalMethods = null,
    ) {
        $this->adviceNames     = $traitAdviceNames;
        $this->originalMethods = $originalMethods ?? self::indexMethods($originalTrait);

        $dynamicMethodAdvices = $traitAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices  = $traitAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $interceptedMethods   = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $propertyAdvices      = $traitAdviceNames[AspectContainer::PROPERTY_PREFIX] ?? [];

        // Register the imports up front, so that every generated reference below uses the final alias.
        $this->imports = ProxyImports::forClass($originalTrait, $originalImports);
        $this->imports->reserve(self::shortClassName($parentTraitName));
        $this->imports->import(InterceptorInjector::class);
        $this->imports->import(Interceptor::class);
        $this->imports->import(The::class);
        foreach ($this->collectAspectClasses($traitAdviceNames) as $aspectClass) {
            $this->imports->import($aspectClass);
        }
        $this->importMethodInvocationTypes($originalTrait, $interceptedMethods);
        if (!empty($propertyAdvices)) {
            $this->imports->import(FieldAccess::class);
        }

        $generatedMethods     = $this->interceptMethods($originalTrait, $interceptedMethods);
        $generatedProperties  = [];
        foreach ($propertyAdvices as $propertyName => $adviceNames) {
            $property = $originalTrait->getProperty($propertyName);
            $generatedProperties[] = (new TraitInterceptedPropertyGenerator($property, $adviceNames, $this->imports))->getNode();
        }

        $docComment = $originalTrait->getDocComment();
        $docBlock   = $docComment !== false ? DocBlockGenerator::fromDocComment($docComment) : null;

        $methodGenerators = array_map(
            static fn($m) => $m->getGenerator(),
            array_values($generatedMethods),
        );
        $traitGenerator = new TraitGenerator(
            $originalTrait->getShortName(),
            $originalTrait->getNamespaceName(),
            $methodGenerators,
            $docBlock,
            $generatedProperties,
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
            $traitGenerator->addTraitAlias($fullName, $methodName . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX, Visibility::Private);
        }

        foreach ($this->imports->getUses() as $className => $alias) {
            $traitGenerator->addUse($className, $alias);
        }

        // Store generator instance for compatibility with parent generate() call
        $this->generator = $traitGenerator;
    }

    /**
     * In a trait proxy, all intercepted methods always have a private `<method>OriginalAlias` alias in the
     * trait-use block (from the parent trait). So the callable always references the alias.
     */
    protected function createOriginalMethodCallable(ReflectionMethod $method, ?ReflectionClass $originalClass): Expr
    {
        return self::createTraitAliasCallable($method);
    }

    public function addUse(string $use, ?string $useAlias = null): void
    {
        if ($use !== '' && $this->generator instanceof TraitGenerator) {
            $this->generator->addUse($use, $useAlias !== '' ? $useAlias : null);
        }
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    public function generateStmts(): array
    {
        return $this->generator->getStmts();
    }
}
