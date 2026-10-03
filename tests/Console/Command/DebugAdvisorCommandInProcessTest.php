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

use Go\Aop\Advice;
use Go\Aop\Advisor;
use Go\Core\AdviceMatcher;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;
use Go\Core\Cache\CachedAspectLoader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the debug:advisor command.
 *
 * The AspectKernel is a per-process singleton, so the kernel is replaced with a
 * test double; the fixture-project behaviour stays covered by the functional
 * DebugAdvisorCommandTest which shells out.
 */
class DebugAdvisorCommandInProcessTest extends TestCase
{
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new DebugAdvisorCommand();

        $this->assertSame('debug:advisor', $command->getName());
        $this->assertSame('Provides an interface for checking and debugging advisors', $command->getDescription());
        $this->assertTrue($command->getDefinition()->hasOption('advisor'));
    }

    public function testExecuteListsRegisteredAdvisors(): void
    {
        $advisor = $this->createStub(Advisor::class);
        $advisor->method('getAdvice')->willReturn($this->createStub(Advice::class));

        $aspectLoader = $this->createStub(CachedAspectLoader::class);
        $aspectLoader->method('getUnloadedAspects')->willReturn([]);

        $container = $this->createMock(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [CachedAspectLoader::class, $aspectLoader],
        ]);
        $container->expects($this->once())
            ->method('getServicesByInterface')
            ->with(Advisor::class)
            ->willReturn(['test.advisor' => $advisor]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Advisor debug information', $tester->getDisplay());
        $this->assertStringContainsString('test.advisor', $tester->getDisplay());
    }

    public function testShowAdvisorsListRegistersUnloadedAspects(): void
    {
        $aspect = $this->createStub(\Go\Aop\Aspect::class);

        $aspectLoader = $this->createMock(CachedAspectLoader::class);
        $aspectLoader->method('getUnloadedAspects')->willReturn([$aspect]);
        $aspectLoader->expects($this->once())->method('loadAndRegister')->with($aspect);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [CachedAspectLoader::class, $aspectLoader],
        ]);
        $container->method('getServicesByInterface')->willReturn([]);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testShowAdvisorInformationThrowsForInvalidAdvisor(): void
    {
        $aspectLoader = $this->createStub(CachedAspectLoader::class);
        $aspectLoader->method('getUnloadedAspects')->willReturn([]);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AdviceMatcher::class, $this->createStub(AdviceMatcher::class)],
            [CachedAspectLoader::class, $aspectLoader],
        ]);
        $container->method('getServicesByInterface')->willReturn([]);
        $container->method('getValue')->willReturn(new stdClass());

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);

        $command = $this->makeCommandWithKernel($kernel);

        $tester = new CommandTester($command);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid advisor Not\\An\\Advisor given');

        $tester->execute(['loader' => 'unused.php', '--advisor' => 'Not\\An\\Advisor']);
    }

    public function testShowAdvisorInformationScansMatchingClasses(): void
    {
        $advisorId = 'test.advisor';
        $advisor   = $this->createStub(Advisor::class);

        $adviceMatcher = $this->createStub(AdviceMatcher::class);
        $adviceMatcher->method('getAdvicesForClass')->willReturnCallback(
            function (ReflectionClass $class) {
                if ($class->getName() === 'Go\Tests\TestProject\Aspect\LoggingAspect') {
                    return ['method' => ['beforeMethod' => 'advice']];
                }

                return [];
            },
        );

        $aspectLoader = $this->createStub(CachedAspectLoader::class);
        $aspectLoader->method('getUnloadedAspects')->willReturn([]);

        $container = $this->createStub(AspectContainer::class);
        $container->method('getService')->willReturnMap([
            [AdviceMatcher::class, $adviceMatcher],
            [CachedAspectLoader::class, $aspectLoader],
        ]);
        $container->method('getServicesByInterface')->willReturn([]);
        $container->method('getValue')->willReturn($advisor);

        $aspectDir = realpath(__DIR__ . '/../../Fixtures/project/src/Aspect');
        $this->assertNotFalse($aspectDir);

        $kernel = $this->createStub(AspectKernel::class);
        $kernel->method('getContainer')->willReturn($container);
        $kernel->method('getOptions')->willReturn([
            'appDir'       => $aspectDir,
            'includePaths' => [],
            'excludePaths' => [],
        ]);

        $command = $this->makeCommandWithKernel($kernel);

        $tester   = new CommandTester($command);
        $exitCode = $tester->execute(['loader' => 'unused.php', '--advisor' => $advisorId]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('files to analyze', $tester->getDisplay());
        $this->assertStringContainsString(
            'matching method Go\Tests\TestProject\Aspect\LoggingAspect->beforeMethod',
            $tester->getDisplay(),
        );
    }

    private function makeCommandWithKernel(AspectKernel $kernel): DebugAdvisorCommand
    {
        return new class ($kernel) extends DebugAdvisorCommand {
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
