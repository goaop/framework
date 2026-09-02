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

namespace Go\Core;

use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Aop\CompilableToPhp;
use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Framework\TraitIntroductionInfo;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\AndPointcut;
use Go\Aop\Pointcut\AttributePointcut;
use Go\Aop\Pointcut\ClassInheritancePointcut;
use Go\Aop\Pointcut\MatchInheritedPointcut;
use Go\Aop\Pointcut\ModifierPointcut;
use Go\Aop\Pointcut\NamePointcut;
use Go\Aop\Pointcut\NotPointcut;
use Go\Aop\Pointcut\OrPointcut;
use Go\Aop\Pointcut\PointcutReference;
use Go\Aop\Pointcut\ReturnTypePointcut;
use Go\Aop\Pointcut\TruePointcut;
use Go\Aop\Support\GenericPointcutAdvisor;
use Go\Tests\TestProject\Annotation\Loggable;
use Go\Tests\TestProject\Application\BehaviorTrait;
use Go\Tests\TestProject\Application\FooInterface;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class AdvisorCacheCompilerTest extends TestCase
{
    private AdvisorCacheCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new AdvisorCacheCompiler();
    }

    /**
     * Builds a realistic graph of loaded aspect items covering every emission case:
     * a pointcut-driven advisor with combinators, an introduction advisor,
     * a bare pointcut entry and a pointcut reference.
     *
     * @return array<string, Pointcut|Advisor>
     */
    private function createRealisticItems(): array
    {
        $aspect        = new DoSomethingAspect();
        $adviceClosure = new ReflectionMethod(DoSomethingAspect::class, 'afterDoSomething')->getClosure($aspect);
        $interceptor   = new AfterInterceptor(
            $adviceClosure,
            7,
            'execution(public Go\Tests\TestProject\Application\*->doSomething(*))',
        );

        $executionPointcut = new OrPointcut(
            new AndPointcut(
                Pointcut::KIND_METHOD,
                new NamePointcut(Pointcut::KIND_ALL, 'Go\Tests\TestProject\Application\*', true),
                new ModifierPointcut(ReflectionMethod::IS_PUBLIC)->notMatch(ReflectionMethod::IS_STATIC),
                new NamePointcut(Pointcut::KIND_METHOD, 'doSomething'),
            ),
            new AndPointcut(
                Pointcut::KIND_METHOD,
                new NotPointcut(new AttributePointcut(Pointcut::KIND_METHOD, Loggable::class)),
                new ReturnTypePointcut('string|int'),
                new NamePointcut(Pointcut::KIND_METHOD, 'doSomethingElse'),
                new ClassInheritancePointcut(FooInterface::class),
                new MatchInheritedPointcut(),
            ),
        );

        $introductionPointcut = new AndPointcut(
            Pointcut::KIND_INTRODUCTION | Pointcut::KIND_CLASS,
            new NamePointcut(Pointcut::KIND_ALL, 'Go\Tests\TestProject\Application\**', true),
        );

        return [
            DoSomethingAspect::class . '->afterDoSomething' => new GenericPointcutAdvisor($executionPointcut, $interceptor),
            DoSomethingAspect::class . '->introduction'     => new GenericPointcutAdvisor(
                $introductionPointcut,
                new TraitIntroductionInfo(BehaviorTrait::class, FooInterface::class),
            ),
            DoSomethingAspect::class . '->methodPointcut'   => new TruePointcut(Pointcut::KIND_METHOD),
            DoSomethingAspect::class . '->referencedItem'   => new PointcutReference(DoSomethingAspect::class . '->methodPointcut'),
        ];
    }

    public function testCompilesRealisticGraphToExpectedPhpFile(): void
    {
        $content = $this->compiler->compile(DoSomethingAspect::class, $this->createRealisticItems());

        $this->assertStringEqualsFile(__DIR__ . '/_files/compiled-advisor-cache.php', $content);
    }

    public function testCompilationIsByteDeterministic(): void
    {
        $firstPass  = $this->compiler->compile(DoSomethingAspect::class, $this->createRealisticItems());
        $secondPass = $this->compiler->compile(DoSomethingAspect::class, $this->createRealisticItems());

        $this->assertSame($firstPass, $secondPass);
    }

    public function testCompilesClassInheritancePointcutWithClassConstFetch(): void
    {
        $content = $this->compiler->compile(DoSomethingAspect::class, [
            DoSomethingAspect::class . '->childrenPointcut' => new ClassInheritancePointcut(FooInterface::class),
        ]);

        // An existing parent class/interface name is emitted as a ::class constant fetch
        $this->assertStringContainsString('new ClassInheritancePointcut(FooInterface::class)', $content);
        $this->assertStringContainsString('use Go\Tests\TestProject\Application\FooInterface;', $content);
    }

    public function testCompilesClassInheritancePointcutParentWithoutExistenceChecks(): void
    {
        // The parent name is a class name by construction, so a ::class fetch is emitted
        // unconditionally - triggering autoloading via class_exists() during compilation
        // is not an option, and ::class on a not-yet-loaded name is a plain compile-time string
        $content = $this->compiler->compile(DoSomethingAspect::class, [
            DoSomethingAspect::class . '->childrenPointcut' => new ClassInheritancePointcut('Some\NotYetLoaded\ParentClass'),
        ]);

        $this->assertStringContainsString('new ClassInheritancePointcut(ParentClass::class)', $content);
        $this->assertStringContainsString('use Some\NotYetLoaded\ParentClass;', $content);
    }

    public function testCompilesMatchInheritedPointcutWithoutArguments(): void
    {
        $content = $this->compiler->compile(DoSomethingAspect::class, [
            DoSomethingAspect::class . '->inheritedPointcut' => new MatchInheritedPointcut(),
        ]);

        $this->assertStringContainsString('new MatchInheritedPointcut()', $content);
    }

    public function testCompilesPlainStringAndIntegerLikeAdvisorKeys(): void
    {
        $content = $this->compiler->compile(DoSomethingAspect::class, [
            // Not of the "Fqcn->member" form, so no ::class concatenation is possible
            'custom.pointcut.id' => new TruePointcut(),
            // PHP casts integer-like string keys to int keys
            '42'                 => new TruePointcut(),
        ]);

        $this->assertStringContainsString("'custom.pointcut.id' => new TruePointcut()", $content);
        $this->assertStringContainsString('42 => new TruePointcut()', $content);
    }

    public function testCompilesEmptyItemsToEmptyAdvisorsArray(): void
    {
        $content = $this->compiler->compile(DoSomethingAspect::class, []);

        $this->assertStringContainsString("'advisors' => []", $content);
    }

    public function testThrowsForInterceptorAdviceNotScopedToAnAspect(): void
    {
        // The closure is scoped to this test case, which is not an Aspect implementation
        $interceptor = new AfterInterceptor(function (): void {});

        $this->expectException(\Go\Aop\AspectException::class);
        $this->expectExceptionMessage('Could not compile an interceptor without valid aspect');

        $interceptor->compileToPhp();
    }

    public function testThrowsForUnknownInterceptorSubclass(): void
    {
        $aspect        = new DoSomethingAspect();
        $adviceClosure = new ReflectionMethod(DoSomethingAspect::class, 'afterDoSomething')->getClosure($aspect);
        // A custom interceptor subclass has no known factory facade counterpart
        $interceptor = new class ($adviceClosure) extends \Go\Aop\Framework\AbstractInterceptor {
            public function invoke(\Go\Aop\Intercept\Joinpoint $joinpoint): mixed
            {
                return $joinpoint->proceed();
            }

            public function getType(): \Go\Aop\AdviceTypeEnum
            {
                return \Go\Aop\AdviceTypeEnum::Around;
            }
        };

        $this->expectException(NotCompilableException::class);

        $interceptor->compileToPhp();
    }

    public function testThrowsForItemNotImplementingCompilableToPhp(): void
    {
        $notCompilableAdvisor = new class implements Advisor {
            public function getAdvice(): Advice
            {
                throw new \LogicException('Not expected to be called');
            }
        };

        $this->expectException(NotCompilableException::class);

        $this->compiler->compile(DoSomethingAspect::class, [
            DoSomethingAspect::class . '->custom' => $notCompilableAdvisor,
        ]);
    }

    public function testCompiledPointcutsRoundTripThroughInclude(): void
    {
        $items = [
            DoSomethingAspect::class . '->executionPointcut' => new AndPointcut(
                Pointcut::KIND_METHOD,
                new NamePointcut(Pointcut::KIND_METHOD, 'doSomething|doSomethingElse'),
                new ModifierPointcut(ReflectionMethod::IS_PUBLIC),
            ),
            DoSomethingAspect::class . '->truePointcut'      => new TruePointcut(),
        ];

        $fileName = tempnam(sys_get_temp_dir(), 'goaop-advisor-cache-');
        $this->assertIsString($fileName);
        try {
            file_put_contents($fileName, $this->compiler->compile(DoSomethingAspect::class, $items));
            $loadedData = include $fileName;
        } finally {
            unlink($fileName);
        }

        $this->assertIsArray($loadedData);
        $this->assertSame(AdvisorCacheCompiler::VERSION, $loadedData['version']);
        $this->assertIsArray($loadedData['advisors']);
        // Advisor ids must be byte-identical to the ids produced by the direct aspect loader
        $this->assertSame(array_keys($items), array_keys($loadedData['advisors']));

        $reflectionClass = new ReflectionClass(DoSomethingAspect::class);
        foreach ($items as $itemId => $originalPointcut) {
            $loadedPointcut = $loadedData['advisors'][$itemId];
            $this->assertInstanceOf($originalPointcut::class, $loadedPointcut);
            $this->assertSame($originalPointcut->getKind(), $loadedPointcut->getKind());
            $this->assertSame(
                $originalPointcut->matches($reflectionClass),
                $loadedPointcut->matches($reflectionClass),
            );
            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                $this->assertSame(
                    $originalPointcut->matches($reflectionClass, $reflectionMethod),
                    $loadedPointcut->matches($reflectionClass, $reflectionMethod),
                    "Matching parity for {$itemId} on method {$reflectionMethod->getName()}",
                );
            }
        }
    }

    /**
     * Every concrete pointcut implementation must know how to compile itself,
     * otherwise real-world aspects would silently lose advisor caching.
     */
    public function testEveryConcretePointcutImplementationIsCompilable(): void
    {
        $pointcutSourceFiles = glob(__DIR__ . '/../../src/Aop/Pointcut/*.php');
        $this->assertNotEmpty($pointcutSourceFiles);

        foreach ($pointcutSourceFiles as $pointcutSourceFile) {
            $className = 'Go\Aop\Pointcut\\' . basename($pointcutSourceFile, '.php');
            if (!class_exists($className)) {
                continue;
            }
            $reflectionClass = new ReflectionClass($className);
            if ($reflectionClass->isAbstract() || !$reflectionClass->implementsInterface(Pointcut::class)) {
                continue;
            }
            $this->assertTrue(
                $reflectionClass->implementsInterface(CompilableToPhp::class),
                "Pointcut implementation {$className} must implement CompilableToPhp",
            );
        }
    }
}
