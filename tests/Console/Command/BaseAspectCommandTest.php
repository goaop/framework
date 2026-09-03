<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2025, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Console\Command;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Instrument\ClassLoading\CacheWarmer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Minimal concrete kernel used only to occupy AspectKernel's process-wide
 * singleton slot for the duration of a single test. Its constructor is
 * `final protected`, so instances are created via
 * ReflectionClass::newInstanceWithoutConstructor() instead of `new`.
 */
final class BaseAspectCommandTestKernelStub extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void {}
}

/**
 * Minimal concrete command exposing the shared BaseAspectCommand internals
 * (loaded kernel, created cache warmer) for direct assertions.
 */
final class BaseAspectCommandTestCommandStub extends BaseAspectCommand
{
    public ?AspectKernel $exposedKernel = null;
    public ?CacheWarmer $exposedWarmer  = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->loadAspectKernel($input, $output);
        $this->exposedKernel = $this->aspectKernel;
        $this->exposedWarmer = $this->createCacheWarmer($output);

        return Command::SUCCESS;
    }
}

/**
 * In-process tests for the shared BaseAspectCommand logic (loader argument
 * parsing, kernel loading and cache warmer creation), exercised through a
 * minimal concrete subclass.
 */
class BaseAspectCommandTest extends TestCase
{
    private function makeCommand(): BaseAspectCommandTestCommandStub
    {
        return new BaseAspectCommandTestCommandStub();
    }

    public function testConfigureAddsLoaderArgument(): void
    {
        $command = $this->makeCommand();

        $this->assertTrue($command->getDefinition()->hasArgument('loader'));
    }

    public function testLoaderArgumentMustBeAString(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Loader argument must be a string');

        // ArrayInput preserves scalar types, so a non-string value reaches loadAspectKernel() as-is.
        $tester->execute(['loader' => 123]);
    }

    public function testLoaderPathMustBeReadable(): void
    {
        $tester = new CommandTester($this->makeCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loader path');

        $tester->execute(['loader' => '/path/to/missing/loader.php']);
    }

    public function testLoaderAssignsRunningKernelInstanceAndCreatesWarmer(): void
    {
        $kernel = (new ReflectionClass(BaseAspectCommandTestKernelStub::class))->newInstanceWithoutConstructor();

        // AspectKernel::getInstance() is a process-wide singleton (self::$instance is shared
        // across all subclasses); temporarily point it at our stand-in kernel and restore it
        // afterward so this test does not leak state into the rest of the suite.
        $instanceProperty = new ReflectionProperty(AspectKernel::class, 'instance');
        $instanceProperty->setAccessible(true);
        $previousInstance = $instanceProperty->getValue();
        $instanceProperty->setValue(null, $kernel);

        // loadAspectKernel() wraps the include in ob_start()/ob_clean() without ever popping the
        // buffer level (ob_clean() empties it but keeps it open). That is harmless for a real,
        // short-lived CLI process, but leaves an extra output-buffering level active for the rest
        // of this shared PHPUnit process, so track and restore the level around the call.
        $obLevelBefore = ob_get_level();

        try {
            $command  = $this->makeCommand();
            $tester   = new CommandTester($command);
            $exitCode = $tester->execute(['loader' => __DIR__ . '/../../../vendor/autoload.php']);

            $this->assertSame(Command::SUCCESS, $exitCode);
            $this->assertSame($kernel, $command->exposedKernel);
            $this->assertInstanceOf(CacheWarmer::class, $command->exposedWarmer);
        } finally {
            $instanceProperty->setValue(null, $previousInstance);
            while (ob_get_level() > $obLevelBefore) {
                ob_end_clean();
            }
        }
    }
}
