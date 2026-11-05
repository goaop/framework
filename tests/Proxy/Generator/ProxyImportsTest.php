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

namespace Go\Proxy\Generator;

use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\The;
use Go\ParserReflection\ReflectionFile;
use Go\Stubs\Collision\A\SameNameAspect as AspectA;
use Go\Stubs\Collision\B\SameNameAspect as AspectB;
use PHPUnit\Framework\TestCase;

final class ProxyImportsTest extends TestCase
{
    public function testUsesShortNamesWhenNothingCollides(): void
    {
        $imports = new ProxyImports('App');

        $this->assertSame('Interceptor', $imports->import(Interceptor::class));
        $this->assertSame('The', $imports->import(The::class));
        // Repeated imports return the same name
        $this->assertSame('Interceptor', $imports->import('\\' . Interceptor::class));
        $this->assertSame([Interceptor::class => null, The::class => null], $imports->getUses());
    }

    public function testAliasesFrameworkClassesCollidingWithOriginalImports(): void
    {
        $imports = new ProxyImports('App', ['App\\Log\\Interceptor' => 'Interceptor']);

        $this->assertSame('AopInterceptor', $imports->import(Interceptor::class));
        // Generated imports come first, the original imports keep their explicit alias
        $this->assertSame(
            [Interceptor::class => 'AopInterceptor', 'App\\Log\\Interceptor' => 'Interceptor'],
            $imports->getUses(),
        );
    }

    public function testReusesAliasOfFrameworkClassImportedByOriginalFile(): void
    {
        $imports = new ProxyImports('App', [InterceptorInjector::class => 'Injector']);

        $this->assertSame('Injector', $imports->import(InterceptorInjector::class));
        $this->assertSame([InterceptorInjector::class => 'Injector'], $imports->getUses());
    }

    public function testAliasesClassesSharingShortNameByTheirNamespace(): void
    {
        $imports = new ProxyImports('App');

        $this->assertSame('SameNameAspect', $imports->import(AspectA::class));
        $this->assertSame('BSameNameAspect', $imports->import(AspectB::class));
    }

    public function testFallsBackToNumericSuffix(): void
    {
        $imports = new ProxyImports('App', ['App\\One\\Interceptor' => null, 'App\\Two' => 'AopInterceptor']);

        $this->assertSame('AopInterceptor2', $imports->import(Interceptor::class));
    }

    public function testReservedNamesAreCaseInsensitive(): void
    {
        $imports = new ProxyImports('App');
        $imports->reserve('THE');

        $this->assertSame('AopThe', $imports->import(The::class));
    }

    public function testGlobalClassesAreImportedOnlyIntoNamespacedFiles(): void
    {
        $namespaced = new ProxyImports('App');
        $global     = new ProxyImports();

        $this->assertSame('GlobalAspect', $namespaced->import('GlobalAspect'));
        $this->assertSame(['GlobalAspect' => null], $namespaced->getUses());
        $this->assertSame('GlobalAspect', $global->import('GlobalAspect'));
        $this->assertSame([], $global->getUses());
    }

    public function testReservesClassNameAndNamesUsedInClassBody(): void
    {
        $file  = new ReflectionFile('collision.php', $this->parse(<<<'PHP'
            <?php
            namespace App;

            class Interceptor
            {
                public function run(int $level = The::LEVEL, \Fully\Qualified\InterceptorInjector $ignored = null): void
                {
                    Sub\FieldAccessType::call();
                }
            }
            PHP));
        $class = $file->getFileNamespace('App')->getClass('App\\Interceptor');

        $imports = ProxyImports::forClass($class);

        $this->assertSame('AopInterceptor', $imports->import(Interceptor::class));
        $this->assertSame('AopThe', $imports->import(The::class));
        // Fully qualified names do not bind short names, qualified ones bind their first segment
        $this->assertSame('InterceptorInjector', $imports->import(InterceptorInjector::class));
        $this->assertSame('AopSub', $imports->import('Go\\Aop\\Intercept\\Sub'));
    }

    /**
     * @return array<\PhpParser\Node\Stmt>
     */
    private function parse(string $code): array
    {
        $nodes = (new \PhpParser\ParserFactory())->createForHostVersion()->parse($code);
        $this->assertIsArray($nodes);

        return $nodes;
    }
}
