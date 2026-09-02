<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2017, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Tests\TestProject\Application\ArrayPropertyDemo;
use Go\Tests\TestProject\Application\AbstractBar;
use Go\Tests\TestProject\Application\ClassWithComplexTypes;
use Go\Tests\TestProject\Application\FinalClass;
use Go\Tests\TestProject\Application\FooInterface;
use Go\Tests\TestProject\Application\Main;
use Go\Tests\TestProject\Application\NewInInitializerClass;
use Go\Tests\TestProject\Application\PromotedPropertyClass;
use Go\Tests\TestProject\Application\SingleLinePromotedClass;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ClassWeavingTest extends BaseFunctionalTestCase
{
    /**
     * The advisor cache must be compiled into plain-PHP shadow files mirroring the aspect
     * sources, and compilation must be byte-deterministic across full cache rebuilds.
     */
    public function testAdvisorCacheShadowFilesAreCompiledDeterministically(): void
    {
        $collectShadowFiles = function (): array {
            $cacheDir = \Go\Instrument\PathResolver::realpath($this->configuration['cacheDir']);
            assert(is_string($cacheDir));
            $shadowFiles = [];
            $iterator    = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS),
            );
            foreach ($iterator as $fileInfo) {
                assert($fileInfo instanceof \SplFileInfo);
                if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.cache.php')) {
                    $shadowFiles[$fileInfo->getPathname()] = file_get_contents($fileInfo->getPathname());
                }
            }
            ksort($shadowFiles, SORT_STRING);

            return $shadowFiles;
        };

        // setUp() has already warmed the cache once
        $firstPassFiles = $collectShadowFiles();
        $this->assertNotEmpty($firstPassFiles, 'Warmup must produce *.cache.php advisor shadow files');
        // Shadow files mirror the aspect sources below the cache directory
        $this->assertArrayHasKey(
            \Go\Instrument\PathResolver::realpath($this->configuration['cacheDir']) . '/src/Aspect/LoggingAspect.cache.php',
            $firstPassFiles,
        );

        // A full rebuild from scratch must produce byte-identical shadow files
        $this->clearCache();
        $this->warmUp();

        $this->assertSame($firstPassFiles, $collectShadowFiles());
    }

    public function testPropertyWeaving(): void
    {
        // it weaves Main class public and protected properties
        $this->assertPropertyWoven(Main::class, 'publicClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
        $this->assertPropertyWoven(Main::class, 'protectedClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');

        // it also weaves the private property declared in Main itself: the pointcut asks for
        // private|protected|public and ClassProxyGenerator::interceptProperties() includes
        // IS_PRIVATE for own-class properties (only parent private properties are excluded).
        // The previous "not woven" expectation only ever passed because ClassAdvisorIdentifier
        // received its constructor arguments in the wrong order, making the assertion vacuous.
        $this->assertPropertyWoven(Main::class, 'privateClassProperty', 'Go\\Tests\\TestProject\\Aspect\\PropertyInterceptAspect->interceptClassProperty');
    }

    /**
     * test for https://github.com/goaop/framework/issues/335
     */
    public function testItDoesNotWeaveAbstractMethods(): void
    {
        // it weaves Main class
        $this->assertClassIsWoven(Main::class);

        // it weaves Main class methods
        $this->assertMethodWoven(Main::class, 'doSomething', 'Go\\Tests\\TestProject\\Aspect\\LoggingAspect->beforeMethod', 0);
        $this->assertMethodWoven(Main::class, 'doSomething', 'Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething', 1);
        $this->assertMethodWoven(Main::class, 'doSomethingElse', 'Go\\Tests\\TestProject\\Aspect\\DoSomethingAspect->afterDoSomething');

        // it does not weaves AbstractBar class
        $this->assertClassIsNotWoven(AbstractBar::class);
    }

    public function testClassInitializationWeaving(): void
    {
        $this->assertClassInitializationWoven(Main::class, 'Go\\Tests\\TestProject\\Aspect\\InitializationAspect->beforeInstanceInitialization');
        $this->assertClassStaticInitializationWoven(Main::class, 'Go\\Tests\\TestProject\\Aspect\\InitializationAspect->afterClassStaticInitialization');
    }

    public function testItWeavesFinalClasses(): void
    {
        // it weaves FinalClass class
        $this->assertClassIsWoven(FinalClass::class);

        /* @see FinalClass::somePublicMethod */
        // it weaves somePublicMethod
        $this->assertMethodWoven(FinalClass::class, 'somePublicMethod');

        /* @see FinalClass::someFinalPublicMethod() */
        // it should match and weave someFinalPublicMethod
        $this->assertMethodWoven(FinalClass::class, 'someFinalPublicMethod');

        /* @see ParentWithFinalMethod::someFinalParentMethod() */
        // it should not match with parent final method in the class
        $this->assertMethodNotWoven(FinalClass::class, 'someFinalParentMethod');
    }

    public function testItDoesNotWeaveInterfaces(): void
    {
        $this->assertClassIsNotWoven(FooInterface::class);
    }

    public function testItDoesWeaveMethodWithComplexTypes(): void
    {
        // it weaves ClassWithComplexTypes class
        $this->assertClassIsWoven(ClassWithComplexTypes::class);

        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithUnionTypeReturn');
        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithIntersectionTypeReturn');
        $this->assertMethodWoven(ClassWithComplexTypes::class, 'publicMethodWithDNFTypeReturn');
    }

    /**
     * Promoted constructor properties must be weavable (issue #599): the promoted parameter
     * is demoted to a plain parameter in the woven trait and the property is re-declared
     * with interception hooks in the proxy — for multi-line and single-line constructors.
     */
    public function testPromotedPropertyWeaving(): void
    {
        $this->assertPropertyWoven(
            PromotedPropertyClass::class,
            'name',
            'Go\\Tests\\TestProject\\Aspect\\PromotedPropertyInterceptAspect->beforePromotedNameAccess',
        );
        $this->assertPropertyWoven(
            SingleLinePromotedClass::class,
            'tag',
            'Go\\Tests\\TestProject\\Aspect\\PromotedPropertyInterceptAspect->beforePromotedTagAccess',
        );
    }

    /**
     * An intercepted promoted property whose default is a new-in-initializer expression
     * must weave into loadable code (issue #616): the proxy hook property must not carry
     * the `new` default (illegal in property initializers). The runtime subprocess loads
     * the woven class, instantiates it without arguments and reads the property, proving
     * that the constructor default still materializes through the injected assignment.
     */
    public function testNewInInitializerPromotedPropertyWeaving(): void
    {
        $this->assertPropertyWoven(
            NewInInitializerClass::class,
            'bag',
            'Go\\Tests\\TestProject\\Aspect\\PromotedPropertyInterceptAspect->beforeNewInInitializerBagAccess',
        );

        $phpExecutable = (new PhpExecutableFinder())->find();
        $script = sprintf(
            'include %s; $instance = new %s(); echo implode(",", $instance->getBagItems());',
            var_export($this->configuration['frontController'], true),
            '\\' . NewInInitializerClass::class,
        );
        assert($phpExecutable !== false);
        $process = new Process(
            [$phpExecutable, '-r', $script],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Loading the woven class failed: ' . $process->getOutput() . $process->getErrorOutput(),
        );
        $this->assertSame('seed', trim($process->getOutput()));
    }

    public function testArrayPropertyInterceptionAllowsIndirectModification(): void
    {
        $this->assertPropertyWoven(
            ArrayPropertyDemo::class,
            'indirectModificationCheck',
            'Go\\Tests\\TestProject\\Aspect\\ArrayPropertyInterceptAspect->aroundArrayFieldAccess',
        );

        $demo = new ArrayPropertyDemo();

        $this->assertSame(6, $demo->countItems());
        $demo->appendValue(10);
        $this->assertSame(7, $demo->countItems());
    }
}
