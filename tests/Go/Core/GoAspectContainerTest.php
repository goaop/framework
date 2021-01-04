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
use Go\Aop\Pointcut;
use PHPUnit\Framework\TestCase;

class GoAspectContainerTest extends TestCase
{
    protected GoAspectContainer $container;

    protected function setUp(): void
    {
        $this->container = new GoAspectContainer();
        $this->container->set('kernel.options', []);
        $this->container->set('kernel.interceptFunctions', false);
    }

    /**
     * Tests that all services are registered
     *
     * @dataProvider internalServicesList
     */
    public function testAllServicesAreConfigured(string $serviceId): void
    {
        $service = $this->container->get($serviceId);
        $this->assertNotNull($service);
    }

    public function internalServicesList(): array
    {
        return [
            ['aspect.loader'],
            ['aspect.advice_matcher'],
            ['aspect.annotation.cache'],
            ['aspect.annotation.reader'],
            ['aspect.pointcut.lexer'],
            ['aspect.pointcut.parser'],
        ];
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testPointcutCanBeRegisteredAndReceived(): void
    {
        $pointcut = $this->createMock(Pointcut::class);
        $this->container->registerPointcut($pointcut, 'test');

        $this->assertSame($pointcut, $this->container->getPointcut('test'));
        // Verify that tag is working
        $pointcuts = $this->container->getByTag('pointcut');
        $this->assertSame(['pointcut.test' => $pointcut], $pointcuts);
    }

    /**
     * Tests that pointcut can be registered and accessed
     */
    public function testAdvisorCanBeRegistered(): void
    {
        $advisor = $this->createMock(Advisor::class);
        $this->container->registerAdvisor($advisor, 'test');

        // Verify that tag is working
        $advisors = $this->container->getByTag('advisor');
        $this->assertSame(['advisor.test' => $advisor], $advisors);
    }

    /**
     * Tests that aspect can be registered and accessed
     */
    public function testAspectCanBeRegisteredAndReceived(): void
    {
        $aspect = $this->createMock(Aspect::class);
        $aspectClass = get_class($aspect);

        $this->container->registerAspect($aspect);

        $this->assertSame($aspect, $this->container->getAspect($aspectClass));
        // Verify that tag is working
        $aspects = $this->container->getByTag('aspect');
        $this->assertSame(["aspect.{$aspectClass}" => $aspect], $aspects);
    }

    /**
     * Tests that container resources can be added and isFresh works correctly
     */
    public function testResourceManagement(): void
    {
        // Without resources this should be always true
        $isFresh = $this->container->isFresh(time());
        $this->assertTrue($isFresh);

        $this->container->addResource(__FILE__);
        $realMtime = filemtime(__FILE__);
        $isFresh = $this->container->isFresh($realMtime - 3600);
        $this->assertFalse($isFresh);

        $isFresh = $this->container->isFresh($realMtime + 3600);
        $this->assertTrue($isFresh);
    }
}
