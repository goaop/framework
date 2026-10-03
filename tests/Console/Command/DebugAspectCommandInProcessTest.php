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

use Go\Aop\Advisor;
use Go\Aop\Aspect;
use Go\Aop\Pointcut;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\AspectLoader;
use Go\Tests\TestProject\Aspect\LoggingAspect;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the debug:aspect command.
 *
 * The AspectKernel is a per-process singleton, so the kernel is replaced with a
 * test double; the fixture-project behaviour stays covered by the functional
 * DebugAspectCommandTest which shells out.
 */
class DebugAspectCommandInProcessTest extends TestCase
{
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new DebugAspectCommand();

        $this->assertSame('debug:aspect', $command->getName());
        $this->assertSame('Provides an interface for querying the information about aspects', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('aspect'));
    }

    public function testExecuteListsEnabledAspects(): void
    {
        $container = $this->createMock(AspectContainer::class);
        $container->expects($this->once())
            ->method('getServicesByInterface')
            ->with(Aspect::class)
            ->willReturn([]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Aspect debug information', $tester->getDisplay());
        $this->assertStringContainsString('has following enabled aspects', $tester->getDisplay());
    }

    public function testExecuteWithUnknownAspectOptionFailsWithSuggestion(): void
    {
        $container = $this->createStub(AspectContainer::class);
        $container->method('has')->willReturn(false);
        $container->method('getServicesByInterface')->willReturn([LoggingAspect::class => new LoggingAspect($this->createStub(LoggerInterface::class))]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php', '--aspect' => 'Go\\Tests\\TestProject\\Aspect\\LogingAspect']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Aspect "Go\\Tests\\TestProject\\Aspect\\LogingAspect" is not registered in the kernel.', $display);
        $this->assertStringContainsString('Did you mean "' . LoggingAspect::class . '"?', $display);
        $this->assertStringNotContainsString('has following enabled aspects', $display);
    }

    public function testExecuteFiltersByAspectOptionAndShowsPointcutsAndAdvisors(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $aspect = new LoggingAspect($logger);

        $aspectLoader = $this->createStub(AspectLoader::class);
        $aspectLoader->method('load')->willReturn([
            'a.pointcut' => $this->createStub(Pointcut::class),
            'a.advisor'  => $this->createStub(Advisor::class),
            'a.unknown'  => new stdClass(),
        ]);

        $container = $this->createStub(AspectContainer::class);
        $container->method('has')->willReturn(true);
        $container->method('getService')->willReturnMap([
            [AspectLoader::class, $aspectLoader],
            [LoggingAspect::class, $aspect],
        ]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php', '--aspect' => LoggingAspect::class]);

        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(LoggingAspect::class, $display);
        $this->assertStringContainsString('Defined in:', $display);
        $this->assertStringContainsString('Application logging aspect', $display);
        $this->assertStringContainsString('Pointcuts and advices', $display);
        $this->assertStringContainsString('Pointcut', $display);
        $this->assertStringContainsString('a.pointcut', $display);
        $this->assertStringContainsString('Advisor', $display);
        $this->assertStringContainsString('a.advisor', $display);
        $this->assertStringContainsString('Unknown', $display);
        $this->assertStringContainsString('a.unknown', $display);
    }

    private function makeCommandWithKernel(AspectKernel $kernel): DebugAspectCommand
    {
        return new class ($kernel) extends DebugAspectCommand {
            public function __construct(private readonly AspectKernel $kernel)
            {
                parent::__construct();
            }

            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                $this->aspectKernel = $this->kernel;
            }
        };
    }
}
