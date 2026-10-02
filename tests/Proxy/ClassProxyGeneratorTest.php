<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2018, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Proxy;

use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\PhpUnit\AssertsCompilablePhp;
use Go\Stubs\Collision\A\SameNameAspect as AspectA;
use Go\Stubs\Collision\B\SameNameAspect as AspectB;
use Go\Stubs\ClassWithMixedSources;
use Go\Stubs\First;
use Go\Stubs\FirstStatic;
use Go\Stubs\PropertyInheritanceChild;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test case for generated function definition
 */
class ClassProxyGeneratorTest extends TestCase
{
    use AssertsCompilablePhp;

    /**
     * Test proxy generation for class method
     *
     * @param class-string $className Name of the class to intercept
     * @param string $methodName Name of the method to intercept
     *
     * @throws ReflectionException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dataGenerator')]
    public function testGenerateProxyMethod(string $className, string $methodName): void
    {
        $reflectionClass = new ReflectionClass($className);
        $classAdvices    = [
            'method' => [
                $methodName => [self::testAdvice()],
            ],
        ];

        $childGenerator = new ClassProxyGenerator(
            $reflectionClass,
            'Test',
            $classAdvices,
        );
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        // Proxy uses a trait alias for each intercepted method
        $this->assertStringContainsString(
            "{$methodName}OriginalAlias",
            $proxyFileContent,
            'Proxy must contain trait alias for intercepted method',
        );

        // Proxy intercepted method delegates to the join-point invocation chain
        $this->assertStringContainsString(
            "InterceptorInjector::forMethod(",
            $proxyFileContent,
            'Proxy method body must delegate to the join-point invocation chain',
        );
    }

    /**
     * Imports of the original file may bind short names the generated code needs (Interceptor, The,
     * InterceptorInjector) and two aspects may share a short name: the proxy keeps readable short
     * imports and aliases only the colliding ones, so it still compiles (issue #668).
     *
     * @throws ReflectionException
     */
    public function testGeneratedProxyAliasesImportsCollidingWithOriginalImports(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'method' => [
                'publicMethod' => [
                    GeneratedInterceptor::fromAdvice('a', new BeforeInterceptor(new AspectA()->beforeMethod(...))),
                    GeneratedInterceptor::fromAdvice('b', new BeforeInterceptor(new AspectB()->beforeMethod(...))),
                ],
            ],
            'prop' => [
                'public' => [self::testAdvice()],
            ],
        ];
        $originalImports = [
            'App\\Log\\Interceptor'                     => 'Interceptor',
            'App\\Log\\The'                             => 'The',
            'App\\Log\\SameNameAspect'                  => 'SameNameAspect',
            'Go\\Aop\\Framework\\InterceptorInjector' => 'Injector',
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, $originalImports);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString('use Go\\Aop\\Framework\\Interceptor as AopInterceptor;', $proxyFileContent);
        $this->assertStringContainsString('use Go\\Aop\\Framework\\The as AopThe;', $proxyFileContent);
        $this->assertStringContainsString('use Go\\Stubs\\Collision\\A\\SameNameAspect as ASameNameAspect;', $proxyFileContent);
        $this->assertStringContainsString('use Go\\Stubs\\Collision\\B\\SameNameAspect as BSameNameAspect;', $proxyFileContent);
        $this->assertStringContainsString('static $__joinPoint = Injector::forMethod(', $proxyFileContent);
        $this->assertStringContainsString('static $__joinPoint = Injector::forProperty(', $proxyFileContent);
        $this->assertStringContainsString('AopInterceptor::before(AopThe::aspect(ASameNameAspect::class)', $proxyFileContent);
        $this->assertStringContainsString('AopInterceptor::before(AopThe::aspect(BSameNameAspect::class)', $proxyFileContent);
        $this->assertPhpCompiles($proxyFileContent);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterception(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'prop' => [
                'public'    => [self::testAdvice()],
                'protected' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "public int \$public = ",
            $proxyFileContent,
            'Proxy with property advices must re-declare intercepted properties with native hooks',
        );
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyFileContent);
        $this->assertStringContainsString(
            "/** @var FieldAccess<self, int> \$__joinPoint */",
            $proxyFileContent,
            'Proxy with property advices must route writes through join points in property hooks',
        );
        $this->assertStringContainsString(
            "set {\n            /** @var FieldAccess<self, int> \$__joinPoint */\n            static \$__joinPoint = InterceptorInjector::forProperty(",
            $proxyFileContent,
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterceptionPreservesAsymmetricVisibility(): void
    {
        $target          = new class {
            public protected(set) string $name = 'test';
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'name'  => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            'public protected(set) string $name = \'test\' {',
            $proxyFileContent,
            'Proxy must preserve asymmetric visibility on intercepted properties',
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithClassTypedPropertyUsesFullyQualifiedTypeInFieldAccessPhpDoc(): void
    {
        $target = new class {
            // @phpstan-ignore property.unused (declared only to be intercepted by the generated proxy)
            private \Exception $privateProperty;
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices = [
            'prop' => [
                'privateProperty' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "/** @var FieldAccess<self, \\Exception> \$__joinPoint */",
            $proxyFileContent,
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterceptionThrowsForReadonlyAndHookedProperties(): void
    {
        $target = new class {
            public string $intercepted = 'intercepted';
            public readonly string $readonly;
            public string $alreadyHooked = 'hooked' {
                get {
                    return $this->alreadyHooked;
                }
                set {
                    $this->alreadyHooked = $value;
                }
            }

            public function __construct()
            {
                $this->readonly = 'readonly';
            }
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'intercepted' => [self::testAdvice()],
                'readonly' => [self::testAdvice()],
                'alreadyHooked' => [self::testAdvice()],
            ],
        ];

        $this->expectException(WeavingException::class);
        new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithFinalPropertyDeclaredInCurrentClass(): void
    {
        $target = new class {
            final public string $final = 'final';
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices = [
            'prop' => [
                'final' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString("final public string \$final = 'final' {", $proxyFileContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyFileContent);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithParentPropertyInterceptionIncludesPublicAndProtected(): void
    {
        $reflectionClass = new ReflectionClass(PropertyInheritanceChild::class);
        $classAdvices = [
            'prop' => [
                'parentPublic' => [self::testAdvice()],
                'parentProtected' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString("public string \$parentPublic = 'parent-public' {", $proxyFileContent);
        $this->assertStringContainsString("protected string \$parentProtected = 'parent-protected' {", $proxyFileContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyFileContent);
        $this->assertStringContainsString("InterceptorInjector::forProperty(", $proxyFileContent);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithUninitializedTypedPropertyInterceptionAddsInitializationSafeguard(): void
    {
        $target = new class {
            public string $uninitialized;
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'uninitialized' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {",
            $proxyFileContent,
        );
        $this->assertStringContainsString(
            "return \$__joinPoint->__invoke(\$this, FieldAccessType::Read);",
            $proxyFileContent,
        );
        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {",
            $proxyFileContent,
        );
        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {\n                \$this->uninitialized = \$__joinPoint->__invoke(\$this, FieldAccessType::Write, \$value, \$this->uninitialized);",
            $proxyFileContent,
        );
        $this->assertStringContainsString(
            "} else {\n                \$this->uninitialized = \$__joinPoint->__invoke(\$this, FieldAccessType::Write, \$value);",
            $proxyFileContent,
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithArrayPropertyInterceptionUsesGetHookOnly(): void
    {
        $target = new class {
            /** @var array<int> */
            public array $items = [1, 2, 3];

            public function appendValue(int $value): void
            {
                array_push($this->items, $value);
            }
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'items' => [self::testAdvice()],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertMatchesRegularExpression('/&get\s*\\{/', $proxyFileContent);
        $this->assertStringNotContainsString("FieldAccessType::Write, \$this->items, \$value", $proxyFileContent);
    }

    /**
     * Tests that private instance and static methods are intercepted correctly:
     * - The proxy overrides them with the same `private` visibility
     * - The trait-use block aliases each as `private <method>OriginalAlias`
     * - The method body delegates to the join-point chain
     *
     * This is a new capability in the trait-based engine; the old extend-based engine
     * could not intercept private methods because PHP disallows overriding them in subclasses.
     *
     * @throws ReflectionException
     */
    public function testGenerateInterceptsPrivateMethods(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'method' => [
                'privateMethod'      => [self::testAdvice()], // private function
            ],
            'static' => [
                'staticSelfPrivate'  => [self::testAdvice()], // private static function
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'OriginalBodyTrait', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        // Trait alias must exist for each private method
        $this->assertStringContainsString('privateMethodOriginalAlias', $proxyFileContent);
        $this->assertStringContainsString('staticSelfPrivateOriginalAlias', $proxyFileContent);

        // Proxy methods must keep private visibility
        $this->assertStringContainsString('private function privateMethod(', $proxyFileContent);
        $this->assertStringContainsString('private static function staticSelfPrivate(', $proxyFileContent);

        // Method bodies must call the join-point chain
        $this->assertStringContainsString("InterceptorInjector::forMethod(", $proxyFileContent);
        $this->assertStringContainsString("InterceptorInjector::forStaticMethod(", $proxyFileContent);
    }

    /**
     * Regression test: when an aspect only introduces interfaces/traits (no method advices),
     * the original class body trait must still be included in the proxy's use block.
     * Without this, all original class methods are invisible on the proxy instance.
     *
     * @throws ReflectionException
     */
    public function testGenerateWithIntroductionOnlyAlwaysIncludesOriginalTrait(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        // Only interface/trait introductions — no method, static, or property advices
        $classAdvices = [
            'interface' => ['root' => ['\\Stringable']],
            'trait'     => ['root' => ['\\SomeTrait']],
        ];

        $traitName        = 'OriginalBodyTrait';
        $childGenerator   = new ClassProxyGenerator($reflectionClass, $traitName, $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            $traitName,
            $proxyFileContent,
            'Proxy must use the original class body trait even when no methods are intercepted',
        );
    }

    /**
     * Verifies that a class which uses a trait has both its trait-defined methods AND its own
     * methods correctly intercepted in the generated proxy.
     *
     * When WeavingTransformer converts a class to a trait, the `use SomeTrait` statement moves
     * into the `<Class>OriginalTrait` body. The proxy class itself only uses that trait, so
     * it is unaware of the original trait — but it must still alias and override every method
     * regardless of whether it came from a used trait or was directly declared.
     *
     * @throws ReflectionException
     */
    public function testGenerateProxyForClassUsingTraitMethods(): void
    {
        $reflectionClass = new ReflectionClass(ClassWithMixedSources::class);
        // ClassWithMixedSources uses TraitAliasProxied (publicMethod, protectedMethod, …)
        // and also declares ownPublicMethod directly.
        $classAdvices = [
            'method' => [
                'publicMethod'    => [self::testAdvice()], // defined in TraitAliasProxied
                'ownPublicMethod' => [self::testAdvice()], // defined directly in ClassWithMixedSources
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'ClassWithMixedSourcesOriginalTrait', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        // Both trait-defined and own methods must have trait aliases
        $this->assertStringContainsString('publicMethodOriginalAlias', $proxyFileContent);
        $this->assertStringContainsString('ownPublicMethodOriginalAlias', $proxyFileContent);

        // Both must delegate to the join-point chain
        $this->assertStringContainsString("InterceptorInjector::forMethod(", $proxyFileContent);
        $this->assertStringContainsString("InterceptorInjector::forMethod(", $proxyFileContent);
    }

    /**
     * Regression: inherited methods should still be intercepted, but must not be aliased from the
     * woven trait because the trait only contains methods declared directly in the target class.
     *
     * The generated proxy must name the parent method, `[parent::class, 'method']`, so that
     * {@see DynamicTraitAliasMethodInvocation} can resolve the prototype via reflection.
     *
     * @throws ReflectionException
     */
    public function testGenerateProxyForInheritedMethodDoesNotCreateTraitAlias(): void
    {
        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $classAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice()],
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'FirstStaticOriginalTrait', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        $this->assertStringNotContainsString(
            'FirstStaticOriginalTrait::publicMethod as private publicMethodOriginalAlias',
            $proxyFileContent,
        );
        $this->assertStringContainsString(
            "InterceptorInjector::forMethod(",
            $proxyFileContent,
        );
        // Inherited instance method names the parent method (no `<method>OriginalAlias` available)
        $this->assertStringContainsString(
            "[parent::class, 'publicMethod']",
            $proxyFileContent,
            'Inherited instance method must name the parent method',
        );
    }

    /**
     * Inherited static methods have no trait alias in the proxy (the woven trait only contains
     * methods declared in the intercepted class itself).  The generated proxy must therefore use
     * `parent::method(...)` as the callable so that {@see StaticTraitAliasMethodInvocation} can
     * wrap it in a `forward_static_call` shim for correct late-static-binding support.
     *
     * @throws ReflectionException
     */
    public function testGenerateProxyForInheritedStaticMethodUsesParentCallable(): void
    {
        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $classAdvices    = [
            'static' => [
                'staticSelfPublic' => [self::testAdvice()],
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'FirstStaticOriginalTrait', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        // No trait alias for inherited static method
        $this->assertStringNotContainsString(
            'staticSelfPublicOriginalAlias',
            $proxyFileContent,
            'Inherited static method must not produce a trait alias',
        );

        // Must delegate to the join-point chain
        $this->assertStringContainsString(
            "InterceptorInjector::forStaticMethod(",
            $proxyFileContent,
        );

        // Inherited static method must use parent:: first-class callable
        $this->assertStringContainsString(
            "parent::staticSelfPublic(...)",
            $proxyFileContent,
            'Inherited static method must use parent::method(...) as first-class callable',
        );
    }

    /**
     * Verifies that the #[\Deprecated] attribute on methods is correctly propagated
     * to generated proxy methods.
     *
     * @throws ReflectionException
     */
    public function testGeneratePreservesDeprecatedAttribute(): void
    {
        $target = new class {
            #[\Deprecated("use newMethod() instead")]
            public function oldMethod(): void {}

            public function normalMethod(): void {}
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'method' => [
                'oldMethod'    => [self::testAdvice()],
                'normalMethod' => [self::testAdvice()],
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        // Deprecated attribute must be present on the proxied oldMethod
        $this->assertStringContainsString(
            '#[\Deprecated(',
            $proxyFileContent,
            'Proxy must preserve #[\Deprecated] attribute on the proxied method',
        );
        $this->assertStringContainsString(
            "'use newMethod() instead'",
            $proxyFileContent,
            'Proxy must preserve the deprecation message argument',
        );

        // Count occurrences — should appear exactly once (only on oldMethod, not normalMethod)
        $deprecatedCount = substr_count($proxyFileContent, '#[\Deprecated(');
        $this->assertSame(
            1,
            $deprecatedCount,
            'Deprecated attribute should appear exactly once (only on oldMethod)',
        );
    }

    /**
     * When the trait and the proxy share the same namespace, the generated use-block
     * must reference the trait by its short (unqualified) name, not the FQCN.
     *
     * @throws ReflectionException
     */
    public function testTraitAdoptionUsesShortNameWhenSameNamespace(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice()],
            ],
        ];

        // Trait in the same namespace as the proxy (Go\Stubs)
        $traitFqcn = 'Go\\Stubs\\FirstOriginalTrait';
        $generator = new ClassProxyGenerator($reflectionClass, $traitFqcn, $classAdvices);
        $output    = "<?php\n" . $generator->generate();

        // Must use the short (unqualified) trait name
        $this->assertStringContainsString('use FirstOriginalTrait {', $output);
        $this->assertStringContainsString('FirstOriginalTrait::publicMethod as private publicMethodOriginalAlias', $output);
        $this->assertStringNotContainsString('\\Go\\Stubs\\FirstOriginalTrait', $output);
    }

    /**
     * When the trait is in a different namespace from the proxy, the generated use-block
     * must keep the FQCN so PHP can resolve the trait correctly.
     *
     * @throws ReflectionException
     */
    public function testTraitAdoptionUsesFqcnWhenDifferentNamespace(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'method' => [
                'publicMethod' => [self::testAdvice()],
            ],
        ];

        // Trait in a different namespace from the proxy (proxy is in Go\Stubs)
        $traitFqcn = 'Other\\Namespace\\FirstOriginalTrait';
        $generator = new ClassProxyGenerator($reflectionClass, $traitFqcn, $classAdvices);
        $output    = "<?php\n" . $generator->generate();

        // Must use the FQCN for the trait name
        $this->assertStringContainsString('use \\Other\\Namespace\\FirstOriginalTrait {', $output);
        $this->assertStringContainsString('\\Other\\Namespace\\FirstOriginalTrait::publicMethod as private publicMethodOriginalAlias', $output);
        $this->assertStringNotContainsString('use FirstOriginalTrait {', $output);
    }

    private static function testAdvice(): GeneratedInterceptor
    {
        return GeneratedInterceptor::fromAdvice('test', new BeforeInterceptor(static function (): void {}));
    }

    /**
     * Provides list of methods with expected attributes
     *
     * @return array<array{class-string, string}>
     */
    public static function dataGenerator(): array
    {
        return [
            [First::class, 'publicMethod'],
            [First::class, 'protectedMethod'],
            [First::class, 'passByReference'],
            [\ClassWithoutNamespace::class, 'publicMethod'],
        ];
    }
}
