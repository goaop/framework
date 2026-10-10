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
use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\WeavingDriver;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Instrument\ZEngine\DonorCacheIndex;
use Go\Instrument\ZEngine\MethodTableRewriter;
use Go\Instrument\ZEngine\WeaveOutcome;
use Go\Instrument\ZEngine\ZEngineClassWeaver;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\ClassProxyGenerator;
use Go\Proxy\DonorClassGenerator;
use Go\Stubs\ZEngine\InProcessAspect;
use Go\Stubs\ZEngine\FallbackTarget;
use Go\Stubs\ZEngine\InProcessKernel;
use Go\Stubs\ZEngine\LateTarget;
use Go\Stubs\ZEngine\MatcherErrorAdvisor;
use Go\Stubs\ZEngine\MatcherErrorAspect;
use Go\Stubs\ZEngine\MatcherErrorTarget;
use Go\Stubs\ZEngine\SelfMatchingAspect;
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
use RuntimeException;

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
        $file = new ReflectionClass(WovenTarget::class)->getFileName();
        $this->assertNotFalse($file);
        $this->assertSame(WeaveOutcome::AlreadyWoven, $weaver->weaveLoadedClass(WovenTarget::class, $file)->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weaveLoadedClass(\ArrayObject::class, $file)->outcome, 'Internal, whatever file the loader names');
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weaveLoadedClass(\Countable::class, $file)->outcome, 'An interface');
        $enum = $weaver->weaveLoadedClass(WeavingDriver::class, $file);
        $this->assertSame(WeaveOutcome::Skipped, $enum->outcome);
        $this->assertSame('interfaces, traits and enums are not supported by the zengine driver', $enum->reason);

        $this->assertSame('pong', (new UnadvisedTarget())->ping());
        $this->assertSame(WeaveOutcome::NoAdvices, $weaver->weave(UnadvisedTarget::class)->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave('Go\Stubs\ZEngine\NeverLoaded')->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\ArrayObject::class)->outcome, 'The result of the load above');
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\Countable::class)->outcome);
        $internal = $weaver->weave(\ArrayIterator::class);
        $this->assertSame(WeaveOutcome::Skipped, $internal->outcome);
        $this->assertSame('the class has no source file (internal or runtime-generated)', $internal->reason);
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

    public function testRecordsOfAPreviousRequestDecideWithoutTheAspects(): void
    {
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();
        $index     = $container->getService(DonorCacheIndex::class);
        $source    = realpath(self::STUBS_DIR . '/UnadvisedTarget.php');
        $this->assertNotFalse($source);

        // A file the loader included that did not declare the class it was asked for
        $weaver = $container->getService(ZEngineClassWeaver::class);
        $result = $weaver->weaveLoadedClass('Go\\Stubs\\ZEngine\\NotDeclaredAnywhere', $source);
        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('the file did not declare the class', $result->reason);

        // A "no advices" record is served without the matcher
        $index->recordNoAdvices(UnadvisedTarget::class, $source);
        $index->flush();
        $warmWeaver = $this->weaverWithFreshIndex($kernel);
        require_once $source;
        $this->assertSame(WeaveOutcome::NoAdvices, $warmWeaver->weaveLoadedClass(UnadvisedTarget::class, $source)->outcome);

        // A donor record whose file does not declare the donor class is a broken cache
        $donorFile = $index->donorFileFor($source, 'UnadvisedTarget');
        $container->getService(CachePathManager::class)->getCacheFileWriter()->write($donorFile, "<?php\n// no class here\n");
        $index->recordDonor(UnadvisedTarget::class, $source, $donorFile, UnadvisedTarget::class . '__AopDonor', ['ping']);
        $index->flush();
        $brokenWeaver = $this->weaverWithFreshIndex($kernel);
        try {
            $brokenWeaver->weaveLoadedClass(UnadvisedTarget::class, $source);
            self::fail('A donor file without the donor class must be reported');
        } catch (WeavingException $failure) {
            $this->assertStringContainsString('does not declare the donor class', $failure->getMessage());
        }
        // The failure is remembered for the class, whichever entry point asks again
        try {
            $brokenWeaver->weaveLoadedClass(UnadvisedTarget::class, $source);
            self::fail('The remembered failure must be rethrown');
        } catch (WeavingException $rethrown) {
            $this->assertStringContainsString('does not declare the donor class', $rethrown->getMessage());
        }
        $this->expectException(WeavingException::class);
        $brokenWeaver->weave(UnadvisedTarget::class);
    }

    public function testAnAspectIsNeverWovenEvenWhenItsOwnPointcutMatchesIt(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);

        $this->assertSame('helper', new SelfMatchingAspect()->helper());
        $result = $weaver->weave(SelfMatchingAspect::class);

        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('aspects are never woven', $result->reason);
    }

    public function testAPointcutFailingOnAnOrdinaryClassFailsItsLoad(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);

        try {
            class_exists(MatcherErrorTarget::class);
            self::fail('The matching failure must fail the load');
        } catch (RuntimeException $failure) {
            $this->assertSame(MatcherErrorAdvisor::FAILURE . ' ' . MatcherErrorTarget::class, $failure->getMessage());
        }
        $this->assertTrue(class_exists(MatcherErrorTarget::class, false), 'The class is declared, but not woven');
        $this->assertSame('ran', new MatcherErrorTarget()->run());

        // The failure is remembered for the class
        $this->expectException(RuntimeException::class);
        $weaver->weave(MatcherErrorTarget::class);
    }

    public function testAPointcutFailingOnAnAspectDoesNotFailItsLoad(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);

        $this->assertSame('helper', new MatcherErrorAspect()->helper());
        $result = $weaver->weave(MatcherErrorAspect::class);

        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('aspects are never woven', $result->reason);
    }

    public function testASourceTheParserCannotReadFallsBackToNativeReflection(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ZEngineClassWeaver::class);
        require_once self::STUBS_DIR . '/FallbackTarget.php';
        $brokenSource = realpath(self::STUBS_DIR . '/broken-source.txt');
        $this->assertNotFalse($brokenSource);

        $result = $weaver->weaveLoadedClass(FallbackTarget::class, $brokenSource);

        $this->assertSame(WeaveOutcome::Woven, $result->outcome);
        $this->assertSame(['twice'], $result->methods);
        $this->assertSame(6, new FallbackTarget()->twice(3));
        $this->assertSame([FallbackTarget::class . '::twice'], InProcessAspect::$log);
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
        $weaver         = $this->weaverWithFreshIndex($kernel);
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
     * A weaver as the next request would build it: its own index instance, reading the flushed index file
     */
    private function weaverWithFreshIndex(InProcessKernel $kernel): ZEngineClassWeaver
    {
        $container = $kernel->getContainer();

        return new ZEngineClassWeaver(
            $container,
            $container->getService(AdviceMatcher::class),
            $container->getService(CachedAspectLoader::class),
            $container->getService(CachePathManager::class),
            new DonorCacheIndex($container->getService(CachePathManager::class), $kernel->getOptions()),
            $container->getService(MethodTableRewriter::class),
        );
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
