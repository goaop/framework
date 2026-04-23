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

use Go\Stubs\ClassWithMixedSources;
use Go\Stubs\First;
use Go\Stubs\FirstStatic;
use Go\Stubs\PropertyInheritanceChild;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

/**
 * Test case for generated function definition
 */
class ClassProxyGeneratorTest extends TestCase
{
    /**
     * Test proxy generation for class method
     *
     * @param string $className Name of the class to intercept
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
                $methodName => ['test']
            ]
        ];

        $childGenerator = new ClassProxyGenerator(
            $reflectionClass,
            'Test',
            $classAdvices,
            false
        );
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        // Proxy uses a trait alias for each intercepted method
        $this->assertStringContainsString(
            "__aop__{$methodName}",
            $proxyFileContent,
            'Proxy must contain trait alias for intercepted method'
        );

        // Proxy intercepted method delegates to the join-point invocation chain
        $this->assertStringContainsString(
            "\\Go\\Aop\\Framework\\InterceptorInjector::forMethod(self::class, '{$methodName}'",
            $proxyFileContent,
            'Proxy method body must delegate to the join-point invocation chain'
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithPropertyInterception(): void
    {
        $reflectionClass = new ReflectionClass(First::class);
        $classAdvices    = [
            'prop' => [
                'public'    => ['test'],
                'protected' => ['test'],
            ]
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "public int \$public = ",
            $proxyFileContent,
            'Proxy with property advices must re-declare intercepted properties with native hooks'
        );
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forProperty(self::class, 'public'", $proxyFileContent);
        $this->assertStringContainsString(
            "/** @var \\Go\\Aop\\Intercept\\FieldAccess<self, int>|null \$__joinPoint */",
            $proxyFileContent,
            'Proxy with property advices must route writes through join points in property hooks'
        );
        $this->assertStringContainsString(
            "set {\n            /** @var \\Go\\Aop\\Intercept\\FieldAccess<self, int>|null \$__joinPoint */\n            static \$__joinPoint;",
            $proxyFileContent
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
                'name'  => ['test'],
            ]
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            'public protected(set) string $name = \'test\' {',
            $proxyFileContent,
            'Proxy must preserve asymmetric visibility on intercepted properties'
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithClassTypedPropertyUsesFullyQualifiedTypeInFieldAccessPhpDoc(): void
    {
        $target = new class {
            private \Exception $privateProperty;
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices = [
            'prop' => [
                'privateProperty' => ['test'],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "/** @var \\Go\\Aop\\Intercept\\FieldAccess<self, \\Exception>|null \$__joinPoint */",
            $proxyFileContent
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
                'intercepted' => ['test'],
                'readonly' => ['test'],
                'alreadyHooked' => ['test'],
            ]
        ];

        $this->expectException(InvalidArgumentException::class);
        new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
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
                'final' => ['test'],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString("final public string \$final = 'final' {", $proxyFileContent);
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forProperty(self::class, 'final'", $proxyFileContent);
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithParentPropertyInterceptionIncludesPublicAndProtected(): void
    {
        $reflectionClass = new ReflectionClass(PropertyInheritanceChild::class);
        $classAdvices = [
            'prop' => [
                'parentPublic' => ['test'],
                'parentProtected' => ['test'],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString("public string \$parentPublic = 'parent-public' {", $proxyFileContent);
        $this->assertStringContainsString("protected string \$parentProtected = 'parent-protected' {", $proxyFileContent);
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forProperty(self::class, 'parentPublic'", $proxyFileContent);
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forProperty(self::class, 'parentProtected'", $proxyFileContent);
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
                'uninitialized' => ['test'],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {",
            $proxyFileContent
        );
        $this->assertStringContainsString(
            "return \$__joinPoint->__invoke(\$this, \\Go\\Aop\\Intercept\\FieldAccessType::READ);",
            $proxyFileContent
        );
        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {",
            $proxyFileContent
        );
        $this->assertStringContainsString(
            "if (\$__joinPoint->getField()->isInitialized(\$this)) {\n                \$this->uninitialized = \$__joinPoint->__invoke(\$this, \\Go\\Aop\\Intercept\\FieldAccessType::WRITE, \$value, \$this->uninitialized);",
            $proxyFileContent
        );
        $this->assertStringContainsString(
            "} else {\n                \$this->uninitialized = \$__joinPoint->__invoke(\$this, \\Go\\Aop\\Intercept\\FieldAccessType::WRITE, \$value);",
            $proxyFileContent
        );
    }

    /**
     * @throws ReflectionException
     */
    public function testGenerateWithArrayPropertyInterceptionUsesGetHookOnly(): void
    {
        $target = new class {
            public array $items = [1, 2, 3];

            public function appendValue(int $value): void
            {
                array_push($this->items, $value);
            }
        };
        $reflectionClass = new ReflectionClass($target);
        $classAdvices    = [
            'prop' => [
                'items' => ['test'],
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'Test', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertMatchesRegularExpression('/&get\s*\\{/', $proxyFileContent);
        $this->assertStringNotContainsString("FieldAccessType::WRITE, \$this->items, \$value", $proxyFileContent);
    }

    /**
     * Tests that private instance and static methods are intercepted correctly:
     * - The proxy overrides them with the same `private` visibility
     * - The trait-use block aliases each as `private __aop__<method>`
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
                'privateMethod'      => ['test'], // private function
            ],
            'static' => [
                'staticSelfPrivate'  => ['test'], // private static function
            ],
        ];

        $childGenerator   = new ClassProxyGenerator($reflectionClass, 'OriginalBodyTrait', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        // Trait alias must exist for each private method
        $this->assertStringContainsString('__aop__privateMethod', $proxyFileContent);
        $this->assertStringContainsString('__aop__staticSelfPrivate', $proxyFileContent);

        // Proxy methods must keep private visibility
        $this->assertStringContainsString('private function privateMethod(', $proxyFileContent);
        $this->assertStringContainsString('private static function staticSelfPrivate(', $proxyFileContent);

        // Method bodies must call the join-point chain
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forMethod(self::class, 'privateMethod'", $proxyFileContent);
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forStaticMethod(self::class, 'staticSelfPrivate'", $proxyFileContent);
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
        $childGenerator   = new ClassProxyGenerator($reflectionClass, $traitName, $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $childGenerator->generate();

        $this->assertStringContainsString(
            $traitName,
            $proxyFileContent,
            'Proxy must use the original class body trait even when no methods are intercepted'
        );
    }

    /**
     * Verifies that a class which uses a trait has both its trait-defined methods AND its own
     * methods correctly intercepted in the generated proxy.
     *
     * When WeavingTransformer converts a class to a trait, the `use SomeTrait` statement moves
     * into the `__AopProxied` trait body. The proxy class itself only uses `__AopProxied`, so
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
                'publicMethod'    => ['test'], // defined in TraitAliasProxied
                'ownPublicMethod' => ['test'], // defined directly in ClassWithMixedSources
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'ClassWithMixedSources__AopProxied', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        // Both trait-defined and own methods must have trait aliases
        $this->assertStringContainsString('__aop__publicMethod', $proxyFileContent);
        $this->assertStringContainsString('__aop__ownPublicMethod', $proxyFileContent);

        // Both must delegate to the join-point chain
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forMethod(self::class, 'publicMethod'", $proxyFileContent);
        $this->assertStringContainsString("\\Go\\Aop\\Framework\\InterceptorInjector::forMethod(self::class, 'ownPublicMethod'", $proxyFileContent);
    }

    /**
     * Regression: inherited methods should still be intercepted, but must not be aliased from the
     * woven trait because the trait only contains methods declared directly in the target class.
     *
     * @throws ReflectionException
     */
    public function testGenerateProxyForInheritedMethodDoesNotCreateTraitAlias(): void
    {
        $reflectionClass = new ReflectionClass(FirstStatic::class);
        $classAdvices    = [
            'method' => [
                'publicMethod' => ['test'],
            ],
        ];

        $generator        = new ClassProxyGenerator($reflectionClass, 'FirstStatic__AopProxied', $classAdvices, false);
        $proxyFileContent = "<?php" . PHP_EOL . $generator->generate();

        $this->assertStringNotContainsString(
            'FirstStatic__AopProxied::publicMethod as private __aop__publicMethod',
            $proxyFileContent
        );
        $this->assertStringContainsString(
            "\\Go\\Aop\\Framework\\InterceptorInjector::forMethod(self::class, 'publicMethod'",
            $proxyFileContent
        );
    }

    /**
     * Provides list of methods with expected attributes
     *
     * @return array
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
