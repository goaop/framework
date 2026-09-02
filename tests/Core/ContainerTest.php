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
use Go\Tests\TestProject\Aspect\DoSomethingAspect;
use Go\Tests\TestProject\Aspect\EnumMethodAspect;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use stdClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ContainerTest extends TestCase
{
    protected AspectContainer $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $mockKernel = $this->createMock(AspectKernel::class);
        $mockKernel->method('getOptions')->willReturn([
            'debug'          => false,
            'appDir'         => '',
            'cacheDir'       => '/tmp',
            'cacheFileMode'  => 0770,
            'features'       => 0,
            'includePaths'   => [],
            'excludePaths'   => [],
            'containerClass' => Container::class,
        ]);
        $this->container->add(AspectKernel::class, $mockKernel);
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
     * Tests that container resources can be added and isFreshSince works correctly
     */
    public function testResourceManagement(): void
    {
        // Without resources this should be always true
        $isFresh = $this->container->isFreshSince(time());
        $this->assertTrue($isFresh);

        $this->container->add(First::class, new First());
        $filename = (new \ReflectionClass(First::class))->getFileName();
        $this->assertNotFalse($filename);
        $this->assertFileExists($filename);

        $realMtime = filemtime($filename);
        $isFresh = $this->container->isFreshSince($realMtime - 3600);
        $this->assertFalse($isFresh);

        $isFresh = $this->container->isFreshSince(time() + 3600);
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

    public function testLazyServiceIsNotConstructedUntilFirstUse(): void
    {
        $initialized = false;
        $container = new Container();
        $container->addLazyService(PointcutLexer::class, function () use (&$initialized): PointcutLexer {
            $initialized = true;
            return new PointcutLexer();
        });

        // Registration alone must not invoke the factory (nor autoload anything)
        $this->assertTrue($container->has(PointcutLexer::class));
        $this->assertFalse($initialized, 'Factory should not have been called yet');

        // Retrieval hands out a typed, instanceof-correct lazy proxy without running the factory
        $value = $container->getService(PointcutLexer::class);
        $this->assertInstanceOf(PointcutLexer::class, $value);
        $this->assertFalse($initialized, 'Factory should not run on retrieval');
        $this->assertTrue((new \ReflectionClass(PointcutLexer::class))->isUninitializedLazyObject($value));
        $this->assertSame($value, $container->getService(PointcutLexer::class));

        // First real interaction with the object runs the factory exactly once
        $value->lex('public');
        $this->assertTrue($initialized, 'Factory should have been called on first use');
        $this->assertFalse((new \ReflectionClass(PointcutLexer::class))->isUninitializedLazyObject($value));
        $this->assertSame($value, $container->getService(PointcutLexer::class));
    }

    public function testLazyServiceIsTaggedByInterface(): void
    {
        $services = $this->container->getServicesByInterface(AspectLoaderExtension::class);
        $this->assertNotEmpty($services);
    }

    public function testLazyServiceRejectsNonClassNameId(): void
    {
        $this->expectException(AspectException::class);
        $this->expectExceptionMessageMatches('/Lazy service id must be a valid class name/');
        $this->container->addLazyService('kernel.not-a-class', fn(): PointcutLexer => new PointcutLexer());
    }

    public function testAspectRegisteredByClassNameIsConstructedOnFirstUse(): void
    {
        $constructed = false;
        $this->container->registerAspect(
            LoggingAspect::class,
            function () use (&$constructed): LoggingAspect {
                $constructed = true;

                return new LoggingAspect(new NullLogger());
            }
        );

        $this->assertTrue($this->container->has(LoggingAspect::class));
        $this->assertFalse($constructed, 'Aspect should not have been constructed at registration');

        // Retrieval returns an instanceof-correct lazy object, construction is still deferred
        $aspect = $this->container->getService(LoggingAspect::class);
        $this->assertInstanceOf(LoggingAspect::class, $aspect);
        $this->assertFalse($constructed, 'Aspect should not have been constructed by retrieval');

        // First real interaction with the aspect object triggers the factory
        (new \ReflectionClass(LoggingAspect::class))->initializeLazyObject($aspect);
        $this->assertTrue($constructed);
    }

    public function testLazyAspectAppearsInAspectInterfaceQuery(): void
    {
        $this->container->registerAspect(DoSomethingAspect::class);

        $aspects = $this->container->getServicesByInterface(Aspect::class);
        $this->assertArrayHasKey(DoSomethingAspect::class, $aspects);
        $this->assertInstanceOf(DoSomethingAspect::class, $aspects[DoSomethingAspect::class]);
    }

    public function testLazyAspectEnumerationHandsOutUninitializedLazyObjects(): void
    {
        $constructed = false;
        $this->container->registerAspect(
            StatefulTestAspect::class,
            function () use (&$constructed): StatefulTestAspect {
                $constructed = true;

                return new StatefulTestAspect(42);
            }
        );

        $aspects = $this->container->getServicesByInterface(Aspect::class);
        $aspect  = $aspects[StatefulTestAspect::class];

        // instanceof is correct before initialization, and the factory has not run yet
        $this->assertInstanceOf(Aspect::class, $aspect);
        $this->assertInstanceOf(StatefulTestAspect::class, $aspect);
        $this->assertTrue((new \ReflectionClass(StatefulTestAspect::class))->isUninitializedLazyObject($aspect));
        $this->assertFalse($constructed, 'Enumeration should not construct the aspect');

        // A real method call transparently initializes the lazy object and delegates to it
        $this->assertSame(42, $aspect->getState());
        $this->assertTrue($constructed, 'First method call should construct the aspect');
        $this->assertFalse((new \ReflectionClass(StatefulTestAspect::class))->isUninitializedLazyObject($aspect));
    }

    public function testPropertylessServiceFallsBackToEagerConstruction(): void
    {
        // PHP creates lazy objects of property-less classes as already initialized,
        // which would silently skip the factory - the container must construct these eagerly
        $constructed = false;
        $this->container->registerAspect(
            DoSomethingAspect::class,
            function () use (&$constructed): DoSomethingAspect {
                $constructed = true;

                return new DoSomethingAspect();
            }
        );

        $aspect = $this->container->getService(DoSomethingAspect::class);
        $this->assertInstanceOf(DoSomethingAspect::class, $aspect);
        $this->assertTrue($constructed, 'Factory of a property-less service must run at materialization');
    }

    public function testLazyAspectWithRequiredConstructorArgsNeedsFactory(): void
    {
        // LoggingAspect requires a LoggerInterface constructor argument
        $this->container->registerAspect(LoggingAspect::class);

        $this->expectException(AspectException::class);
        $this->expectExceptionMessageMatches('/pass a factory closure/');
        $this->container->getService(LoggingAspect::class);
    }

    public function testLazyAspectMustImplementAspectInterface(): void
    {
        $this->container->registerAspect(stdClass::class);

        $this->expectException(AspectException::class);
        $this->expectExceptionMessageMatches('/must implement/');
        $this->container->getService(stdClass::class);
    }

    public function testReRegisteringPendingFactoryKeepsTagOrderAndReplacesFactory(): void
    {
        // Downstream kernels replace built-in pipeline services by re-registering the
        // same id; the replacement must keep the id's position in the chain order
        $this->container->registerAspect(DoSomethingAspect::class);
        $this->container->registerAspect(EnumMethodAspect::class);

        $replaced = false;
        $this->container->addLazyService(DoSomethingAspect::class, function () use (&$replaced): DoSomethingAspect {
            $replaced = true;

            return new DoSomethingAspect();
        });

        $aspects = $this->container->getServicesByInterface(Aspect::class);
        $this->assertSame([DoSomethingAspect::class, EnumMethodAspect::class], array_keys($aspects));
        // DoSomethingAspect has no instance properties, so it materializes eagerly
        // through the re-registered factory (see testPropertylessServiceFallsBackToEagerConstruction)
        $this->assertTrue($replaced, 'Re-registered factory should have been used');
    }

    public function testInterfaceQuerySurvivesReentrantMaterialization(): void
    {
        // A factory that re-enters getServicesByInterface() consumes other pending
        // factories from under the outer materialization loop (this also happens
        // implicitly when an aspect class is autoloaded through the weaving pipeline)
        $this->container->registerAspect(
            DoSomethingAspect::class,
            function (AspectContainer $container): DoSomethingAspect {
                $container->getServicesByInterface(Aspect::class);

                return new DoSomethingAspect();
            }
        );
        $this->container->registerAspect(EnumMethodAspect::class);

        $aspects = $this->container->getServicesByInterface(Aspect::class);
        $this->assertArrayHasKey(DoSomethingAspect::class, $aspects);
        $this->assertArrayHasKey(EnumMethodAspect::class, $aspects);
    }
}

/**
 * Stateful aspect fixture: has an instance property, so PHP can create a true lazy proxy for it
 */
class StatefulTestAspect implements Aspect
{
    public function __construct(private readonly int $state) {}

    public function getState(): int
    {
        return $this->state;
    }
}
