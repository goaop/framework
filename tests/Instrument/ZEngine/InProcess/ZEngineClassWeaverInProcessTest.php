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

namespace Go\Instrument\ZEngine\InProcess;

use Go\Aop\Exception\UnsupportedJoinpointException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AspectContainer;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ZEngine\DonorCacheIndex;
use Go\Instrument\ZEngine\WeaveOutcome;
use Go\Instrument\ZEngine\ZEngineClassWeaver;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\DonorClassGenerator;
use Go\Stubs\ZEngine\InProcessAspect;
use Go\Stubs\ZEngine\LateTarget;
use Go\Stubs\ZEngine\UnadvisedTarget;
use Go\Stubs\ZEngine\UnsupportedAspect;
use Go\Stubs\ZEngine\UnsupportedTarget;
use Go\Stubs\ZEngine\WarmTarget;
use Go\Stubs\ZEngine\WovenChild;
use Go\Stubs\ZEngine\WovenParent;
use Go\Stubs\ZEngine\WovenTarget;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;

/**
 * Classes are woven through the engine when they are loaded, and on demand
 */
#[Group('zengine')]
final class ZEngineClassWeaverInProcessTest extends ZEngineInProcessTestCase
{
    public function testAnAdvisedClassIsWovenWhenItIsLoaded(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);
        $this->assertFalse(class_exists(WovenTarget::class, false), 'The stub must not be loaded by an earlier test');

        $target = new WovenTarget();

        $this->assertSame('HELLO ANN', $target->greet('Ann'), 'The around advice ran');
        $this->assertSame(1, $target->getCalls(), 'The original body ran under its alias');
        $this->assertSame([WovenTarget::class . '::greet', WovenTarget::class . '::getCalls'], InProcessAspect::$log);
        $this->assertTrue(new ReflectionClass($target)->hasMethod('greetOriginalAlias'), 'The previous body is kept under the alias');

        $result = $weaver->weave(WovenTarget::class);
        $this->assertSame(WeaveOutcome::AlreadyWoven, $result->outcome);
        $this->assertSame(['greet', 'getCalls'], $result->methods);

        $this->assertSame('pong', (new UnadvisedTarget())->ping());
        $this->assertSame(WeaveOutcome::NoAdvices, $weaver->weave(UnadvisedTarget::class)->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave('Go\Stubs\ZEngine\NeverLoaded')->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\ArrayObject::class)->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\Countable::class)->outcome);
        $this->assertSame(WeaveOutcome::NoAdvices, $weaver->weave(self::class)->outcome, 'Within the application root, matched against the advisors');

        // A class whose source lies outside the application root is never woven
        $outsideFile = $this->cacheDir . '/OutsideTarget.php';
        file_put_contents($outsideFile, "<?php\nnamespace Go\\Stubs\\ZEngine;\nfinal class OutsideTarget { public function ping(): string { return 'pong'; } }\n");
        require $outsideFile;
        $outside = $weaver->weave('Go\\Stubs\\ZEngine\\OutsideTarget');
        $this->assertSame(WeaveOutcome::Skipped, $outside->outcome);
        $this->assertSame('sources outside the application root are never woven', $outside->reason);
    }

    public function testInheritedMethodsGetAnOverrideReachingTheParent(): void
    {
        $this->bootKernel();
        $this->assertFalse(class_exists(WovenChild::class, false));

        $child = WovenChild::create();

        $this->assertSame('parent of ' . WovenChild::class, $child->describe());
        $this->assertSame('own with ' . WovenChild::class, $child->own($child));
        $this->assertSame(
            [WovenChild::class . '::create', WovenChild::class . '::describe', WovenChild::class . '::own'],
            InProcessAspect::$log,
        );
        // The override is published into the child, the parent's own method is untouched
        $this->assertSame(WovenChild::class, new ReflectionMethod(WovenChild::class, 'describe')->class);
        $this->assertFalse(new ReflectionClass(WovenParent::class)->hasMethod('describeOriginalAlias'));
        $this->assertTrue(new ReflectionClass(WovenChild::class)->hasMethod('ownOriginalAlias'));
    }

    public function testAClassMatchedByAnUnsupportedJoinpointKindIsRefusedOnLoad(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);

        try {
            class_exists(UnsupportedTarget::class);
            self::fail('The property pointcut must be refused');
        } catch (UnsupportedJoinpointException $refusal) {
            $this->assertStringContainsString(UnsupportedAspect::class . '->beforeCounterAccess', $refusal->getMessage());
            $this->assertStringContainsString('join point kind(s) ' . AspectContainer::PROPERTY_PREFIX, $refusal->getMessage());
        }
        $this->assertTrue(class_exists(UnsupportedTarget::class, false), 'The class is declared, but not woven');

        // The failure is remembered: a later weave of the class fails the same way
        $this->expectException(UnsupportedJoinpointException::class);
        $weaver->weave(UnsupportedTarget::class);
    }

    public function testAClassDeclaredBeforeTheKernelIsWovenOnDemand(): void
    {
        require_once self::STUBS_DIR . '/LateTarget.php';
        $this->assertTrue(class_exists(LateTarget::class, false));
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);

        $result = $weaver->weave(LateTarget::class);

        $this->assertSame(WeaveOutcome::Woven, $result->outcome);
        $this->assertSame(['compute'], $result->methods);
        $this->assertSame(5, (new LateTarget())->compute(2, 3));
        $this->assertSame([LateTarget::class . '::compute'], InProcessAspect::$log);
        $this->assertSame(WeaveOutcome::AlreadyWoven, $weaver->weave(LateTarget::class)->outcome);
    }

    public function testAFreshDonorRecordIsAppliedWithoutLoadingTheAspects(): void
    {
        // A warm cache: the donor and its record, as a previous request would have left them
        $source = realpath(self::STUBS_DIR . '/WarmTarget.php');
        $this->assertNotFalse($source);
        $parsedFile = new ReflectionFile($source);
        $class      = null;
        $imports    = [];
        foreach ($parsedFile->getFileNamespaces() as $namespace) {
            foreach ($namespace->getClasses() as $parsedClass) {
                $class   = $parsedClass;
                $imports = $namespace->getNamespaceAliases();
            }
        }
        $this->assertNotNull($class);
        $generator = new DonorClassGenerator($class, [
            AspectContainer::METHOD_PREFIX => ['answer' => [self::testInterceptor()]],
        ], $imports, ClassProxyGenerator::indexMethods($class));
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();
        $index     = $container->getService(DonorCacheIndex::class);
        $donorFile = $index->donorFileFor($source, 'WarmTarget');
        $container->getService(CachePathManager::class)->getCacheFileWriter()->write($donorFile, "<?php\n" . $generator->generate());
        $index->recordDonor(WarmTarget::class, $source, $donorFile, $generator->getDonorClassName(), ['answer']);
        $index->flush();

        // A weaver of a new request reads the index and applies the record: no aspect gets loaded for it
        $weaver = new ZEngineClassWeaver(
            $container,
            $container->getService(\Go\Core\AdviceMatcher::class),
            $container->getService(CachedAspectLoader::class),
            $container->getService(CachePathManager::class),
            new DonorCacheIndex($container->getService(CachePathManager::class), $kernel->getOptions()),
            $container->getService(\Go\Instrument\ZEngine\MethodTableRewriter::class),
        );
        $unloadedBefore = $container->getService(CachedAspectLoader::class)->getUnloadedAspects();
        $this->assertNotEmpty($unloadedBefore);
        require_once $source;
        $result = $weaver->weaveLoadedClass(WarmTarget::class, $source);

        $this->assertSame(WeaveOutcome::Woven, $result->outcome);
        $this->assertSame(['answer'], $result->methods);
        $this->assertSame(42, (new WarmTarget())->answer());
        $this->assertSame([WarmTarget::class . '::answer'], InProcessAspect::$log);
        $this->assertSame($unloadedBefore, $container->getService(CachedAspectLoader::class)->getUnloadedAspects(), 'The aspects stayed unloaded');
    }

    /**
     * The descriptor of the aspect-method advice, as the aspect loader produces it: the donor then resolves the
     * aspect through the container (The::aspect()), without the aspect loader
     */
    private static function testInterceptor(): GeneratedInterceptor
    {
        return GeneratedInterceptor::fromAdvice(
            'advisor.' . InProcessAspect::class . '->beforeMethod',
            new BeforeInterceptor(new InProcessAspect()->beforeMethod(...)),
        );
    }
}
