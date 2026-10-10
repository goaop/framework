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

namespace Go\Proxy;

use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Core\AspectContainer;
use Go\Proxy\Generator\ClassGenerator;
use Go\Proxy\Generator\ClassModifier;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\MethodGenerator;
use Go\Proxy\Generator\ProxyImports;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name\FullyQualified;
use ReflectionClass;
use ReflectionMethod;

/**
 * Generates the donor class of the z-engine driver: the dispatcher bodies of the advised methods, compiled
 * in a class of their own and adopted into the woven class by the engine
 *
 * The donor `Ns\Foo__AopDonor` of `Ns\Foo` is abstract (never instantiated), lives in the namespace of
 * the woven class with the imports of its file, extends the same parent and implements the same
 * interfaces, and declares exactly the advised methods with the signature, attributes and doc comment
 * of the original: the engine copies the signature along with the body, and checks it against the
 * entry it replaces. The bodies are the ones a proxy class would get, with two differences for code
 * that is compiled outside the woven class:
 *  - the joinpoint names the woven class explicitly (`\Ns\Foo::class`), since `self::class` would be
 *    bound to the donor at compile time;
 *  - the `self` and `parent` keywords of parameter and return types are spelled out as the classes they
 *    stand for in the woven class, for the same reason (`static` keeps binding to the called class).
 * The calls that reach the original body stay dynamic and follow the scope of the swapped entry:
 * `$this-><method>OriginalAlias(...)` for a method the class declares (the engine keeps the previous
 * body under that private alias) and `parent::<method>(...)` for an inherited one.
 *
 * @see \Go\Instrument\ZEngine\MethodTableRewriter The engine side of the contract
 */
final class DonorClassGenerator extends ClassProxyGenerator
{
    /**
     * Fully qualified name of the generated donor class
     */
    private readonly string $donorClassName;

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
     * @throws WeavingException When the advices contain a join-point kind the donor cannot carry
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
            throw new WeavingException(sprintf(
                'A donor class carries method dispatchers only, the advices of %s contain the join-point kind(s) %s',
                $originalClass->getName(),
                implode(', ', array_map(strval(...), $unsupportedKinds)),
            ));
        }

        $this->adviceNames     = $classAdviceNames;
        $this->originalMethods = $originalMethods ?? self::indexMethods($originalClass);
        $this->targetClassName = $originalClass->getName();
        $parentClass           = $originalClass->getParentClass();
        $this->parentClassName = $parentClass !== false ? $parentClass->getName() : null;

        $namespace      = $originalClass->getNamespaceName();
        $donorShortName = $originalClass->getShortName() . AspectContainer::DONOR_CLASS_SUFFIX;
        $this->donorClassName = ($namespace !== '' ? $namespace . '\\' : '') . $donorShortName;

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

        // Never instantiated; a readonly parent (hence a readonly woven class) requires a readonly child
        $modifiers = [ClassModifier::Abstract];
        if ($originalClass->isReadOnly()) {
            $modifiers[] = ClassModifier::Readonly;
        }
        $interfaces = array_map(static fn(string $interface): string => '\\' . $interface, $originalClass->getInterfaceNames());

        $classGenerator = new ClassGenerator(
            $donorShortName,
            $namespace !== '' ? $namespace : null,
            $modifiers,
            $this->parentClassName !== null ? '\\' . $this->parentClassName : null,
            array_values(array_unique($interfaces)),
            [],
            $methodGenerators,
        );
        $classGenerator->docBlock = DocBlockGenerator::fromDocComment(
            '/**' . PHP_EOL
            . ' * Dispatcher bodies woven into \\' . $this->targetClassName . ' by the zengine driver of Go! AOP' . PHP_EOL
            . ' *' . PHP_EOL
            . ' * @internal Generated code, never instantiated: the engine adopts the method bodies' . PHP_EOL
            . ' */',
        );
        foreach ($this->imports->getUses() as $className => $alias) {
            $classGenerator->addUse($className, $alias);
        }

        $this->generator = $classGenerator;
    }

    /**
     * Returns the fully qualified name of the generated donor class
     */
    public function getDonorClassName(): string
    {
        return $this->donorClassName;
    }

    public function generate(): string
    {
        return $this->generator->generate();
    }

    /**
     * The joinpoint is created for the woven class, named explicitly: `self::class` in a donor method would
     * be bound to the donor class when the donor file is compiled
     *
     * @param ReflectionClass<covariant object>|null $originalClass
     */
    protected function createJoinpointClassReference(?ReflectionClass $originalClass): Expr
    {
        return new ClassConstFetch(new FullyQualified($this->targetClassName), 'class');
    }

    /**
     * Spells out the `self` and `parent` keywords of the signature as the classes they name in the woven class
     *
     * The engine checks the donor signature against the one of the replaced entry (with the keywords resolved
     * against the woven class), and a keyword compiled in the donor would name the donor's own hierarchy.
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
