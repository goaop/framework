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

use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\AbstractMethodInvocation;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Aop\InitializationAware;
use Go\Aop\Intercept\ClassJoinpoint;
use Go\Aop\Intercept\ConstructorInvocation;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\StaticMethodInvocation;
use Go\Aop\Proxy;
use Go\Aop\StaticInitializationAware;
use Go\Core\AspectContainer;
use Go\Proxy\Generator\AttributeGroupsGenerator;
use Go\Proxy\Generator\ClassGenerator;
use Go\Proxy\Generator\ClassModifier;
use Go\Proxy\Generator\DocBlockGenerator;
use Go\Proxy\Generator\GeneratorInterface;
use Go\Proxy\Generator\InterceptorListGenerator;
use Go\Proxy\Generator\MethodGenerator;
use Go\Proxy\Generator\ParameterGenerator;
use Go\Proxy\Generator\ProxyImports;
use Go\Proxy\Generator\TypeGenerator;
use Go\Proxy\Generator\ValueGenerator;
use Go\Proxy\Generator\Visibility;
use Go\Proxy\Part\FunctionCallArgumentListGenerator;
use Go\Proxy\Part\InterceptedMethodGenerator;
use Go\Proxy\Part\InterceptedPropertyGenerator;
use Go\Proxy\Part\JoinPointStatementsGenerator;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\VariadicPlaceholder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Class proxy builder that is used to generate a child class from the list of joinpoints
 */
class ClassProxyGenerator
{
    /**
     * List of advices that are used for generation of child
     *
     * @var array<string, array<string, list<string|GeneratedInterceptor>>>
     */
    protected array $adviceNames = [];

    /**
     * Instance of class generator (ClassGenerator or TraitGenerator via TraitProxyGenerator)
     */
    protected GeneratorInterface $generator;

    /**
     * Imports of the generated file: generated code references classes through their aliases
     */
    protected ProxyImports $imports;

    /**
     * Methods of the original class by name
     *
     * @var array<string, ReflectionMethod>
     */
    protected array $originalMethods = [];

    /**
     * Generates a proxy class that wraps the original class body (now a trait) via trait-use.
     *
     * The original class has been converted to a trait named $traitName by WeavingTransformer.
     * The proxy class re-exposes the same name, parent, and interfaces as the original, uses
     * that trait, and aliases each intercepted method as `private <method>OriginalAlias` so the
     * overriding method body can hand the original to its joinpoint as a first-class callable.
     *
     * @param ReflectionClass<covariant object> $originalClass    Original class reflection (before transformation)
     * @param string                  $traitName        FQCN of the generated trait (e.g. Ns\FooOriginalTrait)
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $classAdviceNames List of advices for class
     * @param array<string, string|null> $originalImports Imports of the original file: class name => alias
     * @param array<string, ReflectionMethod>|null $originalMethods Methods of the original class by name, see
     *                                                              {@see self::indexMethods()}; indexed here when null
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $traitName,
        array $classAdviceNames,
        array $originalImports = [],
        ?array $originalMethods = null,
    ) {
        $this->adviceNames     = $classAdviceNames;
        $this->originalMethods = $originalMethods ?? self::indexMethods($originalClass);

        $dynamicMethodAdvices  = $classAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices   = $classAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];
        $propertyAdvices       = $classAdviceNames[AspectContainer::PROPERTY_PREFIX] ?? [];
        $interceptedMethods    = array_keys($dynamicMethodAdvices + $staticMethodAdvices);
        $interceptedProperties = array_keys($propertyAdvices);
        // Introduced names are class names, root them so a global name never resolves in the proxy namespace
        $introducedInterfaces  = array_map(self::rootClassName(...), array_values(array_filter(
            $classAdviceNames[AspectContainer::INTRODUCTION_INTERFACE_PREFIX]['root'] ?? [],
            is_string(...),
        )));
        $introducedTraits      = array_map(self::rootClassName(...), array_values(array_filter(
            $classAdviceNames[AspectContainer::INTRODUCTION_TRAIT_PREFIX]['root'] ?? [],
            is_string(...),
        )));

        $staticInitializationAdvices = $classAdviceNames[AspectContainer::STATIC_INIT_PREFIX]['root'] ?? [];
        $initializationAdvices       = $classAdviceNames[AspectContainer::INIT_PREFIX]['root'] ?? [];

        // Register the imports up front, so that every generated reference below uses the final alias.
        // Determine needed invocation types from actual method signatures, not advice
        // category keys, because callers may place static-method advices under METHOD_PREFIX.
        $this->imports = ProxyImports::forClass($originalClass, $originalImports);
        $this->imports->reserve(self::shortClassName($traitName));
        $this->imports->import(InterceptorInjector::class);
        $this->imports->import(Interceptor::class);
        $this->imports->import(The::class);
        foreach ($this->collectAspectClasses($classAdviceNames) as $aspectClass) {
            $this->imports->import($aspectClass);
        }
        $this->importMethodInvocationTypes($originalClass, $interceptedMethods);
        if ($staticInitializationAdvices !== []) {
            $this->imports->import(ClassJoinpoint::class);
        }
        if ($initializationAdvices !== []) {
            $this->imports->import(ConstructorInvocation::class);
        }
        if (!empty($propertyAdvices)) {
            $this->imports->import(FieldAccess::class);
        }

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
            array_values($generatedMethods),
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
        $parentClassName = $parentClass !== false ? '\\' . $parentClass->getName() : null;

        // Proxy modifiers: preserve final/abstract/readonly from original class.
        $modifiers = [];
        if ($originalClass->isFinal()) {
            $modifiers[] = ClassModifier::Final;
        }
        if ($originalClass->isAbstract()) {
            $modifiers[] = ClassModifier::Abstract;
        }
        if ($originalClass->isReadOnly()) {
            $modifiers[] = ClassModifier::Readonly;
        }

        $classGenerator = new ClassGenerator(
            $originalClass->getShortName(),
            !empty($originalClass->getNamespaceName()) ? $originalClass->getNamespaceName() : null,
            $modifiers,
            $parentClassName,
            $introducedInterfaces,
            $generatedProperties,
            $methodGenerators,
        );

        if ($originalClass->getDocComment()) {
            $classGenerator->docBlock = DocBlockGenerator::fromDocComment($originalClass->getDocComment());
        }

        // Copy PHP 8+ attributes from original class to proxy so that runtime
        // attribute inspection on proxy objects returns the same attributes
        $classAttrGroups = AttributeGroupsGenerator::fromReflector($originalClass);
        if (!empty($classAttrGroups)) {
            $classGenerator->attrGroups = $classAttrGroups;
        }

        // Use the short (unqualified) trait name only when the trait and proxy
        // share the same namespace; otherwise keep the FQCN
        $lastBackslash     = strrpos($traitName, '\\');
        $traitNamespace    = $lastBackslash !== false ? substr($traitName, 0, $lastBackslash) : '';
        $sameNamespace     = $traitNamespace === $originalClass->getNamespaceName();
        $effectiveTraitName = ($sameNamespace && $lastBackslash !== false) ? substr($traitName, $lastBackslash + 1) : $traitName;

        // Always include the original class body trait — even when no methods are intercepted
        // (e.g. introduction-only aspects). addTraitAlias also registers the trait, so this
        // explicit addTraits call only matters when $interceptedMethods is empty.
        $classGenerator->addTraits([$effectiveTraitName]);

        // Alias each intercepted method as private <name>OriginalAlias
        foreach ($interceptedMethods as $methodName) {
            $reflectionMethod = $this->getOriginalMethod($originalClass, $methodName);
            if ($reflectionMethod->class !== $originalClass->name) {
                continue;
            }

            $classGenerator->addTraitAlias($effectiveTraitName, $methodName, $methodName . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX, Visibility::Private);
        }
        // Add any AOP-introduced traits
        $classGenerator->addTraits($introducedTraits);

        foreach ($this->imports->getUses() as $className => $alias) {
            $classGenerator->addUse($className, $alias);
        }

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
        $staticInitializationAdvices = $this->adviceNames[AspectContainer::STATIC_INIT_PREFIX]['root'] ?? [];

        if ($staticInitializationAdvices !== []) {
            $classCode .= "\n" . $this->generator->name . '::__staticInitialization();';
        }

        return $classCode;
    }

    /**
     * Returns list of intercepted method generators for class by method names
     *
     * @param ReflectionClass<covariant object> $originalClass
     * @param string[] $methodNames List of methods to intercept
     *
     * @return InterceptedMethodGenerator[]
     */
    protected function interceptMethods(ReflectionClass $originalClass, array $methodNames): array
    {
        $interceptedMethods = [];
        foreach ($methodNames as $methodName) {
            $reflectionMethod = $this->getOriginalMethod($originalClass, $methodName);
            if ($reflectionMethod->returnsReference()) {
                throw new WeavingException(sprintf(
                    'Method %s::%s() returns by reference and can not be intercepted: the joinpoint returns '
                    . 'values, so the reference would be lost. Exclude it from the pointcut with '
                    . '"&& !matchReturningByReference()".',
                    $originalClass->name,
                    $methodName,
                ));
            }
            $interceptedMethods[$methodName] = new InterceptedMethodGenerator(
                $reflectionMethod,
                $this->getJoinpointInvocationStatements($reflectionMethod, $originalClass),
            );
        }

        return $interceptedMethods;
    }

    /**
     * Indexes the methods of a class by name, so that each intercepted method is found without a linear scan
     *
     * @param ReflectionClass<covariant object> $class
     *
     * @return array<string, ReflectionMethod>
     */
    public static function indexMethods(ReflectionClass $class): array
    {
        $methods = [];
        foreach ($class->getMethods() as $method) {
            $methods[$method->name] ??= $method;
        }

        return $methods;
    }

    /**
     * Returns the method of the original class by name
     *
     * Names missing in the index fall back to the reflection lookup, which keeps its semantics (case-insensitive
     * lookup of native reflection, ReflectionException for an unknown method).
     *
     * @param ReflectionClass<covariant object> $originalClass
     */
    protected function getOriginalMethod(ReflectionClass $originalClass, string $methodName): ReflectionMethod
    {
        return $this->originalMethods[$methodName] ?? $originalClass->getMethod($methodName);
    }

    /**
     * Imports the invocation types of the intercepted methods
     *
     * Determines needed invocation types from actual method signatures, not advice category keys,
     * because callers may place static-method advices under METHOD_PREFIX.
     *
     * @param ReflectionClass<covariant object> $originalClass
     * @param list<string>                      $methodNames
     */
    protected function importMethodInvocationTypes(ReflectionClass $originalClass, array $methodNames): void
    {
        foreach ($methodNames as $methodName) {
            $method = $this->originalMethods[$methodName]
                ?? ($originalClass->hasMethod($methodName) ? $originalClass->getMethod($methodName) : null);
            $this->imports->import($method?->isStatic() === true ? StaticMethodInvocation::class : DynamicMethodInvocation::class);
        }
    }

    /**
     * @param ReflectionClass<covariant object> $originalClass
     * @param array<array-key, list<string|GeneratedInterceptor>> $propertyAdvices
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
            $adviceNames = $propertyAdvices[$property->getName()] ?? [];
            if ($adviceNames === []) {
                continue;
            }
            $interceptedProperties[] = new InterceptedPropertyGenerator($property, $adviceNames, $this->imports);
        }

        return $interceptedProperties;
    }

    /**
     * Builds the statements of an intercepted method body by method reflection
     *
     * @param ReflectionClass<covariant object>|null $originalClass The original class being proxied. When provided,
     *                                                    it is used to determine if the method has a trait alias
     *                                                    (method declared in the class itself) or is inherited
     *                                                    (uses parent:: callable wrapper).
     *
     * @return list<Stmt>
     */
    protected function getJoinpointInvocationStatements(ReflectionMethod $method, ?ReflectionClass $originalClass = null): array
    {
        $isStatic       = $method->isStatic();
        $prefix         = $isStatic ? AspectContainer::STATIC_METHOD_PREFIX : AspectContainer::METHOD_PREFIX;
        $adviceNames    = $this->adviceNames[$prefix][$method->name]
            ?? ($isStatic ? ($this->adviceNames[AspectContainer::METHOD_PREFIX][$method->name] ?? []) : []);
        $invocationType = $this->imports->import($isStatic ? StaticMethodInvocation::class : DynamicMethodInvocation::class);

        $initialization = JoinPointStatementsGenerator::createInitialization(
            $invocationType . '<self' . self::renderReturnTypeForPhpDoc($method) . '>',
            $this->imports->import(InterceptorInjector::class),
            $isStatic ? 'forStaticMethod' : 'forMethod',
            [
                new Arg($this->createJoinpointClassReference($originalClass)),
                new Arg(new String_($method->name)),
                new Arg((new InterceptorListGenerator($adviceNames, $this->imports))->getNode()),
                new Arg($this->createOriginalMethodCallable($method, $originalClass)),
            ],
        );
        $invocationArguments = [
            new Arg($isStatic ? new ClassConstFetch(new Name('static'), 'class') : new Variable('this')),
            ...(new FunctionCallArgumentListGenerator($method))->getArgs(),
        ];

        return [
            $initialization,
            JoinPointStatementsGenerator::createInvocation(
                $invocationArguments,
                JoinPointStatementsGenerator::returnsResult($method),
            ),
        ];
    }

    /**
     * Builds the expression naming the class the joinpoint is created for
     *
     * A proxy class IS the woven class, so `self::class` names it. A generator whose method bodies
     * are compiled inside another class (the z-engine donor) overrides this: PHP resolves
     * `self::class` in a class method to that class at compile time.
     *
     * @param ReflectionClass<covariant object>|null $originalClass
     */
    protected function createJoinpointClassReference(?ReflectionClass $originalClass): Expr
    {
        return new ClassConstFetch(new Name('self'), 'class');
    }

    /**
     * Builds the first-class callable of the original method, which the joinpoint keeps
     *
     * Methods declared in the proxied class have a private `<method>OriginalAlias` alias in the
     * proxy's trait-use block, referenced as `$this->mOriginalAlias(...)` / `self::mOriginalAlias(...)`.
     *
     * Inherited methods have no such alias and are referenced as `parent::method(...)` for both static
     * and dynamic methods. DynamicTraitAliasMethodInvocation dispatches through a ReflectionMethod resolved
     * from the callable, StaticTraitAliasMethodInvocation through a forward_static_call shim that keeps
     * late static binding.
     *
     * @param ReflectionClass<covariant object>|null $originalClass
     */
    protected function createOriginalMethodCallable(ReflectionMethod $method, ?ReflectionClass $originalClass): Expr
    {
        if ($originalClass !== null && $method->class === $originalClass->name) {
            return self::createTraitAliasCallable($method);
        }

        return new StaticCall(new Name('parent'), $method->name, [new VariadicPlaceholder()]);
    }

    /**
     * Builds `$this-><method>OriginalAlias(...)`, or `self::<method>OriginalAlias(...)` for a static method
     */
    protected static function createTraitAliasCallable(ReflectionMethod $method): Expr
    {
        $alias = $method->name . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX;

        return $method->isStatic()
            ? new StaticCall(new Name('self'), $alias, [new VariadicPlaceholder()])
            : new MethodCall(new Variable('this'), $alias, [new VariadicPlaceholder()]);
    }

    /**
     * Renders the return type of the method for the joinpoint `@var` docblock: `, <type>` or an empty string
     *
     * On PHP 8.5+, ReflectionNamedType::getName() resolves 'self'/'parent' to the actual FQCN, so the raw
     * AST return-type node is used when available (goaop/parser-reflection) to preserve keywords; the
     * reflection type is only rendered without it.
     */
    private static function renderReturnTypeForPhpDoc(ReflectionMethod $method): string
    {
        if (!$method->hasReturnType()) {
            return '';
        }
        $node = method_exists($method, 'getNode') ? $method->getNode() : null;
        if ($node instanceof ClassMethod) {
            $astReturnType = $node->getReturnType();

            return $astReturnType !== null ? ', ' . TypeGenerator::renderAstTypeForPhpDoc($astReturnType) : '';
        }

        return ', ' . TypeGenerator::renderTypeForPhpDoc($method->getReturnType());
    }

    /**
     * @param non-empty-list<GeneratedInterceptor|string> $advisorNames
     */
    private function createStaticInitializationMethod(array $advisorNames): MethodGenerator
    {
        $method = new MethodGenerator('__staticInitialization');
        $method->static = true;
        $method->returnType = 'void';
        $method->stmts = [
            JoinPointStatementsGenerator::createInitialization(
                $this->imports->import(ClassJoinpoint::class) . '<self>',
                $this->imports->import(InterceptorInjector::class),
                'forStaticInitialization',
                [
                    new Arg(new ClassConstFetch(new Name('self'), 'class')),
                    new Arg((new InterceptorListGenerator($advisorNames, $this->imports))->getNode()),
                ],
            ),
            new Expression(new FuncCall(
                new Variable(JoinPointStatementsGenerator::JOINPOINT_VARIABLE),
                [new Arg(new ClassConstFetch(new Name('static'), 'class'))],
            )),
        ];

        return $method;
    }

    /**
     * @param non-empty-list<GeneratedInterceptor|string> $advisorNames
     */
    private function createInitializationMethod(array $advisorNames): MethodGenerator
    {
        $method = new MethodGenerator('__initialization');
        $method->static = true;
        $method->returnType = 'static';
        $argumentsParameter = new ParameterGenerator(
            'arguments',
            TypeGenerator::fromTypeString('array'),
            false,
            false,
            new ValueGenerator([]),
        );
        $method->addParameter($argumentsParameter);
        $method->stmts = [
            JoinPointStatementsGenerator::createInitialization(
                $this->imports->import(ConstructorInvocation::class) . '<self>',
                $this->imports->import(InterceptorInjector::class),
                'forInitialization',
                [
                    new Arg(new ClassConstFetch(new Name('self'), 'class')),
                    new Arg((new InterceptorListGenerator($advisorNames, $this->imports))->getNode()),
                ],
            ),
            JoinPointStatementsGenerator::createInvocation([new Arg(new Variable('arguments'))], true),
        ];

        return $method;
    }

    /**
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $adviceNames
     * @return list<string>
     */
    protected function collectAspectClasses(array $adviceNames): array
    {
        $interceptors = [];
        foreach ($adviceNames as $typedAdvices) {
            foreach ($typedAdvices as $concreteAdvices) {
                foreach ($concreteAdvices as $advice) {
                    if ($advice instanceof GeneratedInterceptor) {
                        $interceptors[] = $advice;
                    }
                }
            }
        }

        return InterceptorListGenerator::aspectClasses($interceptors);
    }

    /**
     * Returns the class name without its namespace
     */
    protected static function shortClassName(string $className): string
    {
        $lastSeparator = strrpos($className, '\\');

        return $lastSeparator === false ? $className : substr($className, $lastSeparator + 1);
    }

    /**
     * Returns the class name rooted with a leading backslash, so that it is emitted fully qualified
     */
    private static function rootClassName(string $className): string
    {
        return '\\' . ltrim($className, '\\');
    }
}
