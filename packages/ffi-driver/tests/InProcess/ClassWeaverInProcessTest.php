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

namespace Go\Driver\Ffi\InProcess;

use Go\Aop\AdviceTypeEnum;
use Go\Driver\Ffi\Exception\UnsupportedJoinpointException;
use Go\Aop\Exception\WeavingException;
use Go\Aop\Framework\BeforeInterceptor;
use Go\Aop\Proxy;
use Go\Aop\Framework\GeneratedInterceptor;
use Go\Core\AdviceMatcher;
use Go\Core\AdvisorIndex;
use Go\Core\AspectContainer;
use Go\Core\Cache\CachedAspectLoader;
use Go\Instrument\ClassLoading\CachePathManager;
use Go\Driver\Ffi\DonorCacheIndex;
use Go\Driver\Ffi\MethodTableRewriter;
use Go\Driver\Ffi\WeaveOutcome;
use Go\Driver\Ffi\ClassWeaver;
use Go\ParserReflection\ReflectionFile;
use Go\Proxy\ClassProxyGenerator;
use Go\Driver\Ffi\DonorTraitGenerator;
use Go\Driver\Ffi\Stubs\InProcessAspect;
use Go\Driver\Ffi\Stubs\FallbackTarget;
use Go\Driver\Ffi\Stubs\FunctionTarget;
use Go\Driver\Ffi\Stubs\InProcessKernel;
use Go\Driver\Ffi\Stubs\LateTarget;
use Go\Driver\Ffi\Stubs\MatcherErrorAdvisor;
use Go\Driver\Ffi\Stubs\MatcherErrorAspect;
use Go\Driver\Ffi\Stubs\MatcherErrorTarget;
use Go\Driver\Ffi\Stubs\SelfMatchingAspect;
use Go\Driver\Ffi\Stubs\UnadvisedTarget;
use Go\Driver\Ffi\Stubs\UnsupportedAspect;
use Go\Driver\Ffi\Stubs\UnsupportedTarget;
use Go\Driver\Ffi\Stubs\WarmTarget;
use Go\Driver\Ffi\Stubs\WovenChild;
use Go\Driver\Ffi\Stubs\WovenParent;
use Go\Driver\Ffi\Stubs\WovenTarget;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * Classes are woven through the engine when they are loaded, and on demand
 */
#[Group('ffi')]
final class ClassWeaverInProcessTest extends InProcessTestCase
{
    public function testAnAdvisedClassIsWovenWhenItIsLoaded(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);
        $this->assertFalse(class_exists(WovenTarget::class, false), 'The stub must not be loaded by an earlier test');

        $target = new WovenTarget();

        $this->assertSame('HELLO ANN', $target->greet('Ann'), 'The around advice ran');
        $this->assertSame(1, $target->getCalls(), 'The original body ran under its alias');
        $this->assertSame([WovenTarget::class . '::greet', WovenTarget::class . '::getCalls'], InProcessAspect::$log);
        $this->assertTrue(new ReflectionClass($target)->hasMethod('greetOriginalAlias'), 'The previous body is kept under the alias');
        $this->assertContains(Proxy::class, class_implements($target), 'A woven class is marked like a generated proxy class');

        $result = $weaver->weave(WovenTarget::class);
        $this->assertSame(WeaveOutcome::AlreadyWoven, $result->outcome);
        $this->assertSame(['greet', 'getCalls'], $result->methods);
        $file = new ReflectionClass(WovenTarget::class)->getFileName();
        $this->assertNotFalse($file);
        $this->assertSame(WeaveOutcome::AlreadyWoven, $weaver->weaveLoadedClass(WovenTarget::class, $file)->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weaveLoadedClass(\ArrayObject::class, $file)->outcome, 'Internal, whatever file the loader names');
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weaveLoadedClass(\Countable::class, $file)->outcome, 'An interface');
        $enum = $weaver->weaveLoadedClass(AdviceTypeEnum::class, $file);
        $this->assertSame(WeaveOutcome::Skipped, $enum->outcome);
        $this->assertSame('interfaces, traits and enums are not supported by the ffi driver', $enum->reason);

        $this->assertSame('pong', (new UnadvisedTarget())->ping());
        $this->assertSame(WeaveOutcome::NoAdvices, $weaver->weave(UnadvisedTarget::class)->outcome);
        $this->assertNotContains(Proxy::class, class_implements(new UnadvisedTarget()), 'An unadvised class is not marked');
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave('Go\Driver\Ffi\Stubs\NeverLoaded')->outcome);
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\ArrayObject::class)->outcome, 'The result of the load above');
        $this->assertSame(WeaveOutcome::Skipped, $weaver->weave(\Countable::class)->outcome);
        $internal = $weaver->weave(\ArrayIterator::class);
        $this->assertSame(WeaveOutcome::Skipped, $internal->outcome);
        $this->assertSame('the class has no source file (internal or runtime-generated)', $internal->reason);
        $this->assertSame(WeaveOutcome::NoAdvices, $weaver->weave(self::class)->outcome, 'Within the application root, matched against the advisors');

        // A class whose source lies outside the application root is never woven
        $outsideFile = $this->cacheDir . '/OutsideTarget.php';
        file_put_contents($outsideFile, "<?php\nnamespace Go\\Driver\\Ffi\\Stubs;\nfinal class OutsideTarget { public function ping(): string { return 'pong'; } }\n");
        require $outsideFile;
        $outside = $weaver->weave('Go\\Driver\\Ffi\\Stubs\\OutsideTarget');
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
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);

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
        $weaver = $container->getService(ClassWeaver::class);
        $result = $weaver->weaveLoadedClass('Go\\Driver\\Ffi\\Stubs\\NotDeclaredAnywhere', $source);
        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('the file did not declare the class', $result->reason);

        // A "no advices" record is served without the matcher
        $index->recordNoAdvices(UnadvisedTarget::class, $source);
        $index->flush();
        $warmWeaver = $this->weaverWithFreshIndex($kernel);
        require_once $source;
        $this->assertSame(WeaveOutcome::NoAdvices, $warmWeaver->weaveLoadedClass(UnadvisedTarget::class, $source)->outcome);

        // A donor record whose file does not declare the donor trait is a broken cache
        $donorFile = $index->donorFileFor($source, 'UnadvisedTarget');
        $container->getService(CachePathManager::class)->getCacheFileWriter()->write($donorFile, "<?php\n// no class here\n");
        $index->recordDonor(UnadvisedTarget::class, $source, $donorFile, UnadvisedTarget::class . 'OriginalTrait', ['ping']);
        $index->flush();
        $brokenWeaver = $this->weaverWithFreshIndex($kernel);
        try {
            $brokenWeaver->weaveLoadedClass(UnadvisedTarget::class, $source);
            self::fail('A donor file without the donor class must be reported');
        } catch (WeavingException $failure) {
            $this->assertStringContainsString('does not declare the donor trait', $failure->getMessage());
        }
        // The failure is remembered for the class, whichever entry point asks again
        try {
            $brokenWeaver->weaveLoadedClass(UnadvisedTarget::class, $source);
            self::fail('The remembered failure must be rethrown');
        } catch (WeavingException $rethrown) {
            $this->assertStringContainsString('does not declare the donor trait', $rethrown->getMessage());
        }
        $this->expectException(WeavingException::class);
        $brokenWeaver->weave(UnadvisedTarget::class);
    }

    public function testAnAspectIsNeverWovenEvenWhenItsOwnPointcutMatchesIt(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);

        $this->assertSame('helper', new SelfMatchingAspect()->helper());
        $result = $weaver->weave(SelfMatchingAspect::class);

        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('aspects are never woven', $result->reason);
    }

    public function testAPointcutFailingOnAnOrdinaryClassFailsItsLoad(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);

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
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);

        $this->assertSame('helper', new MatcherErrorAspect()->helper());
        $result = $weaver->weave(MatcherErrorAspect::class);

        $this->assertSame(WeaveOutcome::Skipped, $result->outcome);
        $this->assertSame('aspects are never woven', $result->reason);
    }

    public function testASourceTheParserCannotReadFallsBackToNativeReflection(): void
    {
        $kernel = $this->bootKernel();
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);
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
        $weaver = $kernel->getContainer()->getService(ClassWeaver::class);

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
        $generator = new DonorTraitGenerator($class, [
            AspectContainer::METHOD_PREFIX => ['answer' => [self::testInterceptor()]],
        ], $imports, ClassProxyGenerator::indexMethods($class));
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();
        $index     = $container->getService(DonorCacheIndex::class);
        $donorFile = $index->donorFileFor($source, 'WarmTarget');
        $container->getService(CachePathManager::class)->getCacheFileWriter()->write($donorFile, "<?php\n" . $generator->generate());
        $index->recordDonor(WarmTarget::class, $source, $donorFile, $generator->getDonorTraitName(), ['answer']);
        // ... including the function-proxy record of the namespace, written by the same request
        $index->recordFunctions('Go\\Driver\\Ffi\\Stubs', null, []);
        $index->flush();

        // A weaver of a new request reads the index and applies the records: no aspect gets loaded for it
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
     * The advised internal functions called from the stubs' namespace are shadowed by
     * proxies declared right after the first class of the namespace is loaded; the proxies are declared once per
     * process and the warm index serves them without the aspects
     */
    public function testFunctionsOfANamespaceAreProxiedWhenAClassOfItIsLoaded(): void
    {
        $kernel    = $this->bootKernel();
        $container = $kernel->getContainer();
        $weaver    = $container->getService(ClassWeaver::class);
        $index     = $container->getService(DonorCacheIndex::class);
        $this->assertFalse(class_exists(FunctionTarget::class, false), 'The stub must not be loaded by an earlier test');

        $target = new FunctionTarget();

        // The proxy was declared by the autoload of the class (or by an earlier kernel of this process)
        $this->assertTrue(function_exists('Go\\Driver\\Ffi\\Stubs\\str_rot13'), 'The namespaced proxy shadows the internal function');
        $this->assertSame('uryyb', $target->encode('hello'), 'The proxy reaches the original function');
        $this->assertSame(['around str_rot13 with ["hello"]'], InProcessAspect::$log);
        $this->assertSame('uryyb', $target->encodeQualified('hello'), 'A qualified call reaches the internal function directly');
        $this->assertSame(['around str_rot13 with ["hello"]'], InProcessAspect::$log, 'The qualified call bypassed the proxy');

        $proxyFile = $index->functionsFileFor('Go\\Driver\\Ffi\\Stubs');
        $this->assertSame($proxyFile, $weaver->weaveFunctions('Go\\Driver\\Ffi\\Stubs', self::STUBS_DIR . '/FunctionTarget.php'));
        $this->assertFileExists($proxyFile);
        $this->assertStringContainsString('function str_rot13(', (string) file_get_contents($proxyFile));
        $this->assertNull($weaver->weaveFunctions('', self::STUBS_DIR . '/FunctionTarget.php'), 'The global namespace cannot be proxied');
        $this->assertNull($weaver->weaveFunctions('Go\\Driver\\Ffi\\Unrelated', self::STUBS_DIR . '/FunctionTarget.php'), 'A namespace the file does not declare');
        $this->assertSame($proxyFile, $weaver->weaveFunctions('Go\\Driver\\Ffi\\Stubs', $this->cacheDir . '/Outside.php'), 'Memoized per namespace and request');

        // A warm request reads the record: the proxy file is reused, the functions stay declared
        $index->flush();
        $warm = $this->weaverWithFreshIndex($kernel);
        $this->assertSame($proxyFile, $warm->weaveFunctions('Go\\Driver\\Ffi\\Stubs', self::STUBS_DIR . '/FunctionTarget.php'));
        $this->assertSame('uryyb', $target->encode('hello'));
    }

    /**
     * A weaver as the next request would build it: its own index instance, reading the flushed index file
     */
    private function weaverWithFreshIndex(InProcessKernel $kernel): ClassWeaver
    {
        $container = $kernel->getContainer();

        return new ClassWeaver(
            $container,
            $container->getService(AdviceMatcher::class),
            $container->getService(AdvisorIndex::class),
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
