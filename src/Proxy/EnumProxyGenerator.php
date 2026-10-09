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

use Go\Aop\Framework\AbstractMethodInvocation;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\Aop\Proxy;
use Go\Core\AspectContainer;
use Go\Proxy\Generator\EnumGenerator;
use Go\Proxy\Generator\ProxyImports;
use Go\Proxy\Generator\Visibility;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Enum_ as EnumNode;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionMethod;

/**
 * Enum proxy builder that generates an intercepted enum from a list of joinpoints.
 *
 * PHP enums cannot have properties (static or instance), so this generator uses the same
 * per-method static variable approach as TraitProxyGenerator: each intercepted method body
 * lazily initialises its own `static $__joinPoint` on first call and delegates to it.
 *
 * The original enum has been converted to a trait by WeavingTransformer (cases removed,
 * `enum` keyword replaced by `trait`, backed type stripped). This generator creates a new
 * enum declaration that:
 *   - Declares the same backed type (string|int) or is a pure unit enum
 *   - Implements the same interfaces as the original plus \Go\Aop\Proxy
 *   - Uses the trait and aliases each intercepted method as `private <method>OriginalAlias`
 *   - Re-declares all enum cases (they cannot live in traits)
 *   - Overrides each intercepted method with per-method lazy joinpoint dispatch
 */
final class EnumProxyGenerator extends ClassProxyGenerator
{
    /**
     * Built-in enum methods that must never be intercepted.
     * These are synthesised by PHP and cannot be overridden via trait aliasing.
     */
    private const array BUILTIN_ENUM_METHODS = ['cases', 'from', 'tryFrom'];

    /**
     * Built-in PHP interfaces that are automatically applied to any enum declaration.
     * They must NOT be listed explicitly in the proxy enum's `implements` clause because:
     *  - PHP applies them automatically when the `enum` keyword is used.
     *  - They are not in any namespace, so in a namespaced proxy file they would be
     *    resolved as e.g. `Demo\Example\UnitEnum` instead of the global `\UnitEnum`,
     *    causing a fatal "Interface not found" error.
     */
    private const array BUILTIN_ENUM_INTERFACES = ['UnitEnum', 'BackedEnum'];

    /**
     * Generates a proxy enum that wraps the original enum body (now a trait) via trait-use.
     *
     * Accepts either GoAOP's Go\ParserReflection\ReflectionClass (used at weaving time, before
     * the class is loaded) or a native \ReflectionClass/\ReflectionEnum (used in unit tests where
     * the enum is already in memory). The backing type and cases are resolved via the appropriate
     * API for each case.
     *
     * @param ReflectionClass<covariant object> $originalClass        Original enum reflection (before transformation)
     * @param string                  $traitName            FQCN of the generated trait (e.g. Ns\FooOriginalTrait)
     * @param array<string, array<string, list<string|GeneratedInterceptor>>> $classAdviceNames List of advices for enum
     * @param array<string, string|null> $originalImports Imports of the original file: class name => alias
     * @param array<string, ReflectionMethod>|null $originalMethods Methods of the original enum by name, see
     *                                                              {@see self::indexMethods()}; indexed here when null
     */
    public function __construct(
        ReflectionClass $originalClass,
        string $traitName,
        array $classAdviceNames,
        array $originalImports = [],
        ?array $originalMethods = null,
    ) {
        $this->originalMethods = $originalMethods ?? self::indexMethods($originalClass);
        // Enums cannot be instantiated (no `new EnumClass()`) and cannot have properties, so
        // initialization and property-access join points must never be woven for enums.
        // Filtering them here prevents "Cannot instantiate enum" errors that would occur if
        // an initialization pointcut (e.g. initialization(*)) matches an enum class.
        $classAdviceNames = array_intersect_key($classAdviceNames, [
            AspectContainer::METHOD_PREFIX        => true,
            AspectContainer::STATIC_METHOD_PREFIX => true,
        ]);

        $this->adviceNames = $classAdviceNames;

        $dynamicMethodAdvices = $classAdviceNames[AspectContainer::METHOD_PREFIX] ?? [];
        $staticMethodAdvices  = $classAdviceNames[AspectContainer::STATIC_METHOD_PREFIX] ?? [];

        // Filter out built-in enum methods which cannot be overridden via trait aliasing
        $interceptedMethods = array_values(array_filter(
            array_keys($dynamicMethodAdvices + $staticMethodAdvices),
            static fn(string $m) => !in_array($m, self::BUILTIN_ENUM_METHODS, true),
        ));

        // Register the imports up front, so that every generated reference below uses the final alias.
        $this->imports = ProxyImports::forClass($originalClass, $originalImports);
        $this->imports->reserve(self::shortClassName($traitName));
        $this->imports->import(InterceptorInjector::class);
        $this->imports->import(Interceptor::class);
        $this->imports->import(The::class);
        foreach ($this->collectAspectClasses($classAdviceNames) as $aspectClass) {
            $this->imports->import($aspectClass);
        }
        $this->importMethodInvocationTypes($originalClass, $interceptedMethods);

        $generatedMethods = $this->interceptMethods($originalClass, $interceptedMethods);

        // Proxy implements the same user-defined interfaces as the original enum plus \Go\Aop\Proxy.
        // Built-in PHP enum interfaces (UnitEnum, BackedEnum) are excluded: PHP applies them
        // automatically when the `enum` keyword is used, and listing them explicitly in a
        // namespaced file would resolve them as e.g. Ns\UnitEnum instead of the global \UnitEnum.
        $originalInterfaces = array_filter(
            $originalClass->getInterfaceNames(),
            static fn(string $i) => !in_array($i, self::BUILTIN_ENUM_INTERFACES, true),
        );
        $originalInterfaces   = array_map(static fn(string $i) => '\\' . $i, $originalInterfaces);
        $introducedInterfaces = array_values(array_unique(array_merge($originalInterfaces, ['\\' . Proxy::class])));

        $methodGenerators = array_map(
            static fn($m) => $m->getGenerator(),
            array_values($generatedMethods),
        );

        // Resolve backing type and cases from either GoAOP parser reflection or native ReflectionEnum.
        // GoAOP's ReflectionClass has getNode() and is used at weaving time (before class load).
        // Native ReflectionClass/ReflectionEnum is used in unit tests (class already loaded).
        [$backingType, $enumCases] = $this->resolveEnumData($originalClass);

        $enumGenerator = new EnumGenerator(
            $originalClass->getShortName(),
            !empty($originalClass->getNamespaceName()) ? $originalClass->getNamespaceName() : null,
            $backingType,
            $introducedInterfaces,
            $methodGenerators,
        );

        // Re-declare all enum cases — cases cannot live in traits
        foreach ($enumCases as [$caseName, $caseValue]) {
            $enumGenerator->addEnumCase($caseName, $caseValue);
        }

        // Use the short (unqualified) trait name only when the trait and proxy
        // share the same namespace; otherwise keep the FQCN
        $lastBackslash      = strrpos($traitName, '\\');
        $traitNamespace     = $lastBackslash !== false ? substr($traitName, 0, $lastBackslash) : '';
        $sameNamespace      = $traitNamespace === $originalClass->getNamespaceName();
        $effectiveTraitName = ($sameNamespace && $lastBackslash !== false) ? substr($traitName, $lastBackslash + 1) : $traitName;

        // Always include the original enum body trait
        $enumGenerator->addTraits([$effectiveTraitName]);

        // Alias each intercepted method as private <name>OriginalAlias
        foreach ($interceptedMethods as $methodName) {
            $enumGenerator->addTraitAlias(
                $effectiveTraitName,
                $methodName,
                $methodName . AbstractMethodInvocation::TRAIT_ALIAS_SUFFIX,
                Visibility::Private,
            );
        }

        foreach ($this->imports->getUses() as $className => $alias) {
            $enumGenerator->addUse($className, $alias);
        }

        $this->generator = $enumGenerator;
    }

    /**
     * {@inheritDoc}
     */
    public function addUse(string $use, ?string $useAlias = null): void
    {
        if ($use !== '' && $this->generator instanceof EnumGenerator) {
            $this->generator->addUse($use, $useAlias !== '' ? $useAlias : null);
        }
    }

    /**
     * Generates the enum proxy source code.
     */
    public function generate(): string
    {
        return $this->generator->generate();
    }

    public function generateStmts(): array
    {
        return $this->generator->getStmts();
    }

    /**
     * Every intercepted method keeps its joinpoint in its own `static $__joinPoint` variable, like in
     * a trait proxy: all intercepted enum methods have `<method>OriginalAlias` aliases from the enum's
     * trait-use block, so the callable always references the alias.
     */
    protected function createOriginalMethodCallable(ReflectionMethod $method, ?ReflectionClass $originalClass): Expr
    {
        return self::createTraitAliasCallable($method);
    }

    /**
     * Extracts the backing type and case list from the enum reflection.
     *
     * Supports two reflection sources:
     *  - GoAOP's Go\ParserReflection\ReflectionClass (weaving time, class not yet loaded):
     *    reads from the PhpParser AST node via getNode().
     *  - Native \ReflectionClass / \ReflectionEnum (test context, class already loaded):
     *    uses ReflectionEnum::isBacked(), getBackingType(), and getCases().
     *
     * On the parser-reflection path, backed case values are passed through as raw PhpParser
     * {@see Expr} nodes and re-emitted verbatim by {@see EnumGenerator}. This preserves
     * constant-expression case values (e.g. `-1`, `1 << 2`, `self::SHIFT + 10`) that are not
     * plain string/int literals. `self::CONST` expressions keep resolving on the proxy enum
     * because class constants stay in the woven trait and trait constants participate in the
     * composing class since PHP 8.2.
     *
     * @param ReflectionClass<covariant object> $class
     * @return array{0: string|null, 1: list<array{0: string, 1: string|int|Expr|null}>}
     *   [backingType, [[caseName, caseValue], ...]]
     */
    private function resolveEnumData(ReflectionClass $class): array
    {
        // GoAOP parser reflection path (used at weaving time, before the class is loaded).
        // getNode() is specific to Go\ParserReflection\ReflectionClass.
        if (method_exists($class, 'getNode')) {
            $enumNode    = $class->getNode();
            $backingType = null;
            $cases       = [];

            if ($enumNode instanceof EnumNode) {
                if ($enumNode->scalarType !== null) {
                    $backingType = $enumNode->scalarType->toString();
                }
                foreach ($enumNode->stmts as $stmt) {
                    if (!($stmt instanceof EnumCase)) {
                        continue;
                    }
                    // Pass the raw expression node through so that constant-expression case
                    // values (UnaryMinus, BitwiseShift, self::CONST arithmetic, ...) survive.
                    // Emitting a pure case inside a backed enum would be a PHP fatal error.
                    $cases[] = [$stmt->name->toString(), $stmt->expr];
                }
            }

            return [$backingType, $cases];
        }

        // Native reflection path (used in unit tests where the enum is already loaded).
        /** @var class-string<\UnitEnum> $enumClassName */
        $enumClassName  = $class->getName();
        $enumReflection = new ReflectionEnum($enumClassName);
        $backingTypeObj = $enumReflection->getBackingType();
        $backingType    = $backingTypeObj instanceof \ReflectionNamedType ? $backingTypeObj->getName() : null;
        $cases          = [];
        foreach ($enumReflection->getCases() as $case) {
            $caseValue = $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null;
            $cases[]   = [$case->getName(), $caseValue];
        }

        return [$backingType, $cases];
    }
}
