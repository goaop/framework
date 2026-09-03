<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Support;

use Go\Aop\Framework\AfterInterceptor;
use Go\Aop\Pointcut;
use Go\Core\Container;
use Go\Core\FrameworkServices;
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LazyPointcutAdvisorTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        FrameworkServices::register($this->container);
    }

    public function testGetAdviceReturnsAdvicePassedToConstructor(): void
    {
        $advice  = new AfterInterceptor(static function (): void {});
        $advisor = new LazyPointcutAdvisor($this->container, 'execution(public Foo->bar(*))', $advice);

        $this->assertSame($advice, $advisor->getAdvice());
    }

    public function testGetPointcutParsesExpressionLazilyOnFirstAccess(): void
    {
        $advice  = new AfterInterceptor(static function (): void {});
        $advisor = new LazyPointcutAdvisor($this->container, 'execution(public Foo->bar(*))', $advice);

        $pointcut = $advisor->getPointcut();

        // @phpstan-ignore method.alreadyNarrowedType (runtime double-check of the declared return type)
        $this->assertInstanceOf(Pointcut::class, $pointcut);
    }

    public function testGetPointcutIsMemoizedAcrossCalls(): void
    {
        $advice  = new AfterInterceptor(static function (): void {});
        $advisor = new LazyPointcutAdvisor($this->container, 'execution(public Foo->bar(*))', $advice);

        $first  = $advisor->getPointcut();
        $second = $advisor->getPointcut();

        $this->assertSame($first, $second);
    }

    public function testCompileToPhpEmitsResolvedGenericPointcutAdvisor(): void
    {
        $aspect        = new DoSomethingAspect();
        $adviceClosure = new ReflectionMethod(DoSomethingAspect::class, 'afterDoSomething')->getClosure($aspect);
        $advice        = new AfterInterceptor($adviceClosure);
        $advisor       = new LazyPointcutAdvisor($this->container, 'execution(public Foo->bar(*))', $advice);

        $printer = new Standard();
        $code    = $printer->prettyPrintExpr($advisor->compileToPhp());

        $this->assertStringStartsWith('new \\' . GenericPointcutAdvisor::class . '(', $code);
        $this->assertStringNotContainsString('LazyPointcutAdvisor', $code);
    }
}
