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

use Go\Aop\Aspect;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use PHPUnit\Framework\TestCase;
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

        $command = new class ($kernel) extends DebugAspectCommand {
            public function __construct(private readonly AspectKernel $kernel)
            {
                parent::__construct();
            }

            protected function loadAspectKernel(InputInterface $input, OutputInterface $output): void
            {
                $this->aspectKernel = $this->kernel;
            }
        };

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Aspect debug information', $tester->getDisplay());
        $this->assertStringContainsString('has following enabled aspects', $tester->getDisplay());
    }
}
