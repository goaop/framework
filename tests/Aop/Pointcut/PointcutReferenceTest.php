<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2015, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Aop\Pointcut;

use Go\Aop\AspectException;
use Go\Aop\Pointcut;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Container;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

class PointcutReferenceTest extends TestCase
{
    protected function tearDown(): void
    {
        $instance = new ReflectionProperty(AspectKernel::class, 'instance');
        $instance->setValue(null, null);
    }

    public function testCompileToPhpEmitsNewExpressionWithPointcutId(): void
    {
        $reference = new PointcutReference('some.pointcut.id');

        $printer = new Standard();
        $code    = $printer->prettyPrintExpr($reference->compileToPhp());

        $this->assertSame(
            sprintf("new \\%s('some.pointcut.id')", PointcutReference::class),
            $code,
        );
    }

    public function testMatchesDelegatesToResolvedPointcutFromContainer(): void
    {
        $truePointcut = new TruePointcut(Pointcut::KIND_METHOD);
        $this->initKernelWithContainerValues(['app.pointcut.foo' => $truePointcut]);

        $reference = new PointcutReference('app.pointcut.foo');

        $this->assertTrue($reference->matches(new ReflectionClass(self::class)));
        $this->assertSame(Pointcut::KIND_METHOD, $reference->getKind());
    }

    public function testGetPointcutIsMemoizedAcrossCalls(): void
    {
        $truePointcut = new TruePointcut();
        $this->initKernelWithContainerValues(['app.pointcut.bar' => $truePointcut]);

        $reference = new PointcutReference('app.pointcut.bar');

        // First call resolves and caches the pointcut, second call must reuse it
        // without going through the container again (covered via identical results).
        $this->assertTrue($reference->matches(new ReflectionClass(self::class)));
        $this->assertTrue($reference->matches(new ReflectionClass(self::class)));
        $this->assertSame($truePointcut->getKind(), $reference->getKind());
    }

    public function testGetPointcutThrowsWhenContainerValueIsNotAPointcut(): void
    {
        $this->initKernelWithContainerValues(['app.pointcut.broken' => new stdClass()]);

        $reference = new PointcutReference('app.pointcut.broken');

        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('Reference app.pointcut.broken points not to a Pointcut.');

        $reference->getKind();
    }

    /**
     * @param array<string, mixed> $values
     */
    private function initKernelWithContainerValues(array $values): void
    {
        $kernel    = PointcutReferenceTestAspectKernel::getInstance();
        $container = new Container();
        foreach ($values as $id => $value) {
            $container->add($id, $value);
        }

        $containerProperty = new ReflectionProperty(AspectKernel::class, 'container');
        $containerProperty->setValue($kernel, $container);
    }
}

final class PointcutReferenceTestAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
    }
}
