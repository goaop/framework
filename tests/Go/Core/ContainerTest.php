<?php

declare(strict_types = 1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2013, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Core;

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\AspectException;
use Go\Aop\Pointcut;
use Go\Aop\Pointcut\PointcutLexer;
use Go\Aop\Pointcut\PointcutParser;
use Go\Stubs\First;
use PHPUnit\Framework\TestCase;
use stdClass;

class ContainerTest extends TestCase
{
    protected AspectContainer $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->container->add(AspectKernel::class, $this->createMock(AspectKernel::class));
        $this->container->add('kernel.options', ['cacheDir' => '/tmp']);
        $this->container->add('kernel.interceptFunctions', false);
    }

    /**
     * Tests that all internal services are registered and loadable
     * @param class-string $serviceId
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lazyInternalServices')]
    public function testAllServicesAreConfigured(string $serviceId): void
    {
        $service = $this->container->getService($serviceId);
        $this->assertNotNull($service);
    }

    /**
     * @return class-string[][]
     */
    public static function lazyInternalServices(): array
    {
        return [
            PointcutLexer::class       => [PointcutLexer::class],
            PointcutParser::class      => [PointcutParser::class],
            AdviceMatcher::class       => [AdviceMatcher::class],
            AspectLoader::class        => [AspectLoader::class],
            CachedAspectLoader::class  => [CachedAspectLoader::class],
            LazyAdvisorAccessor::class => [LazyAdvisorAccessor::class],
            // [CachePathManager::class], // Need to politely switch to options instead of whole kernel
        ];
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testPointcutCanBeRegisteredAndReceived(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $this->container->add('test', $pointcut);

        $this->assertSame($pointcut, $this->container->getValue('test'));
        // Verify that tag is working
        $pointcuts = $this->container->getServicesByInterface(Pointcut::class);
        $this->assertSame(['test' => $pointcut], $pointcuts);
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testAdvisorCanBeRegistered(): void
    {
        $advisor = $this->createMock(Advisor::class);
        $this->container->add('test', $advisor);

        $this->assertSame($advisor, $this->container->getValue('test'));

        // Verify that tag is working
        $advisors = $this->container->getServicesByInterface(Advisor::class);
        $this->assertSame(['test' => $advisor], $advisors);
    }

    /**
     * Tests that aspect can be registered and accessed
     */
    public function testAspectCanBeRegisteredAndReceived(): void
    {
        $aspect      = $this->createMock(Aspect::class);
        $aspectClass = $aspect::class;

        $this->container->registerAspect($aspect);

        $this->assertSame($aspect, $this->container->getService($aspectClass));
        // Verify that tag is working
        $aspects = $this->container->getServicesByInterface(Aspect::class);
        $this->assertSame([$aspectClass => $aspect], $aspects);
    }

    /**
     * Tests that container resources can be added and isFresh works correctly
     */
    public function testResourceManagement(): void
    {
        // Without resources this should be always true
        $isFresh = $this->container->hasAnyResourceChangedSince(time());
        $this->assertTrue($isFresh);

        $this->container->add(First::class, new First());
        $filename = (new \ReflectionClass(First::class))->getFileName();
        $this->assertNotFalse($filename);
        $this->assertFileExists($filename);

        $realMtime = filemtime($filename);
        $isFresh = $this->container->hasAnyResourceChangedSince($realMtime - 3600);
        $this->assertFalse($isFresh);

        $isFresh = $this->container->hasAnyResourceChangedSince($realMtime + 3600);
        $this->assertTrue($isFresh);
    }

    public function testHasMethod(): void
    {
        $this->assertFalse($this->container->has('test'));

        $advisor = $this->createMock(Advisor::class);
        $this->container->add('test', $advisor);

        $this->assertTrue($this->container->has('test'));
    }

    public function testGetServiceThrowsOutOfBoundsExceptionOnUnknown(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/Value stdClass is not defined/');
        $this->container->getService(stdClass::class);
    }

    public function testGetValueThrowsOutOfBoundsExceptionOnUnknown(): void
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/Value some.key is not defined/');
        $this->container->getValue('some.key');
    }

    public function testGetServiceEnsuresThatKeyAndReturnedTypeMatches(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessage('Service ' . First::class . ' is not properly registered');

        // Emulation of incorrect types
        $this->container->add(First::class, new stdClass());
        $this->container->getService(First::class);
    }
}
