<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Driver\Ffi;

use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Core\AspectContainer;
use Go\Driver\Ffi\Exception\FfiWeavingException;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\MethodGenerator;
use Go\Proxy\Generator\ProxyImports;
use Go\Proxy\Generator\TraitGenerator;
use ReflectionClass;
use ReflectionMethod;

/**
 * Generates the donor trait of the ffi driver: the dispatcher bodies of the advised methods, compiled in a trait
 * of their own and adopted into the woven class by the engine
 *
 * The donor `Ns\FooOriginalTrait` of `Ns\Foo` is a trait with the structure (and the suffix) of the stream
 * driver's trait codegen: it lives in the namespace of the woven class with the imports of its file and declares
 * exactly the advised methods with the signature, attributes and doc comment of the original, since the engine
 * copies the signature along with the body and checks it against the entry it replaces. The bodies are the ones
 * a proxy class gets: `$this-><method>OriginalAlias(...)` reaches the previous body of a method the class declares
 * (the engine keeps it under that private alias), `parent::<method>(...)` the body of an inherited one, and the
 * joinpoint is created for `self::class`. Compiled in a trait, none of that is bound before run time, where it
 * follows the scope of the swapped entry: the woven class. Only the `self` and `parent` keywords of parameter and
 * return types are spelled out as the classes they name in the woven class: the engine resolves the keywords of a
 * signature against the declaring type when it compares the signatures (`static` keeps binding to the called class).
 *
 * @see MethodTableRewriter The engine side of the contract
 */
final class DonorTraitGenerator extends ClassProxyGenerator
{
    /**
     * Fully qualified name of the generated donor trait
     */
    private readonly string $donorTraitName;

    /**
     * Fully qualified name of the woven class
     */
    private readonly string $targetClassName;

    /**
     * Fully qualified name of the parent of the woven class, null for a class without parent
     */
    private readonly ?string $parentClassName;

    /**
     * @param ReflectionClass<covariant object>                               $originalClass    Woven class
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $classAdviceNames Advices of the class,
     *                                                                                          method kinds only
     * @param array<string, string|null>                                      $originalImports  Imports of the original
     *                                                                                          file: class name => alias
     * @param array<string, ReflectionMethod>|null                            $originalMethods  Methods of the original
     *                                                                                          class by name, see
     *                                                                                          {@see self::indexMethods()}
     *
     * @throws FfiWeavingException When the advices contain a join-point kind the donor cannot carry
     */
    public function __construct(
        ReflectionClass $originalClass,
        array $classAdviceNames,
        array $originalImports = [],
        ?array $originalMethods = null,
    ) {
        $unsupportedKinds = array_diff(
            array_keys($classAdviceNames),
            [AspectContainer::METHOD_PREFIX, AspectContainer::STATIC_METHOD_PREFIX],
        );
        if ($unsupportedKinds !== []) {
            throw FfiWeavingException::unsupportedDonorKinds($originalClass->getName(), array_values(array_map(strval(...), $unsupportedKinds)));
        }

        $this->adviceNames     = $classAdviceNames;
        $this->originalMethods = $originalMethods ?? self::indexMethods($originalClass);
        $this->targetClassName = $originalClass->getName();
        $parentClass           = $originalClass->getParentClass();
        $this->parentClassName = $parentClass !== false ? $parentClass->getName() : null;

        $namespace            = $originalClass->getNamespaceName();
        $donorShortName       = $originalClass->getShortName() . AspectContainer::ORIGINAL_TRAIT_SUFFIX;
        $this->donorTraitName = ($namespace !== '' ? $namespace . '\\' : '') . $donorShortName;

        $dynamicMethodAdvices = $classAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices  = $classAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $interceptedMethods   = array_map(strval(...), array_keys($dynamicMethodAdvices + $staticMethodAdvices));

        // Register the imports up front, so that every generated reference below uses the final alias
        $this->imports = ProxyImports::forClass($originalClass, $originalImports);
        $this->imports->reserve($donorShortName);
        $this->imports->import(InterceptorInjector::class);
        $this->imports->import(Interceptor::class);
        $this->imports->import(The::class);
        foreach ($this->collectAspectClasses($classAdviceNames) as $aspectClass) {
            $this->imports->import($aspectClass);
        }
        $this->importMethodInvocationTypes($originalClass, $interceptedMethods);

        $methodGenerators = [];
        foreach ($this->interceptMethods($originalClass, $interceptedMethods) as $interceptedMethod) {
            $methodGenerators[] = $this->resolveScopeKeywords($interceptedMethod->getGenerator());
        }

        $traitGenerator = new TraitGenerator(
            $donorShortName,
            $namespace !== '' ? $namespace : null,
            $methodGenerators,
            DocBlockGenerator::fromDocComment(
                '/**' . PHP_EOL
                . ' * Dispatcher bodies woven into \\' . $this->targetClassName . ' by the ffi driver of Go! AOP' . PHP_EOL
                . ' *' . PHP_EOL
                . ' * @internal Generated code, never used by a class: the engine adopts the method bodies' . PHP_EOL
                . ' */',
            ),
        );
        foreach ($this->imports->getUses() as $className => $alias) {
            $traitGenerator->addUse($className, $alias);
        }

        $this->generator = $traitGenerator;
    }

    /**
     * Returns the fully qualified name of the generated donor trait
     */
    public function getDonorTraitName(): string
    {
        return $this->donorTraitName;
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    /**
     * Spells out the `self` and `parent` keywords of the signature as the classes they name in the woven class
     *
     * The engine checks the donor signature against the one of the replaced entry with the keywords resolved
     * against the declaring type, and in the donor they would name the trait.
     */
    private function resolveScopeKeywords(MethodGenerator $method): MethodGenerator
    {
        $method->returnType = $method->returnType?->resolveScopeKeywords($this->targetClassName, $this->parentClassName);
        $parameters         = [];
        foreach ($method->getParameters() as $parameter) {
            $type         = $parameter->getType()?->resolveScopeKeywords($this->targetClassName, $this->parentClassName);
            $parameters[] = $parameter->withType($type);
        }
        $method->setParameters($parameters);

        return $method;
    }
}
