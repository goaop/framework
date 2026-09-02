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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the debug:weaving command.
 *
 * The AspectKernel is a per-process singleton, so the weaving consistency checks
 * (including the FAILURE exit code on inconsistent weaving) are covered by the
 * functional DebugWeavingCommandTest which shells out. These tests cover the
 * attribute-based metadata and input validation in-process.
 */
class DebugWeavingCommandInProcessTest extends TestCase
{
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new DebugWeavingCommand();

        $this->assertSame('debug:weaving', $command->getName());
        $this->assertSame('Checks consistency in weaving process', $command->getDescription());
        $this->assertStringContainsString('consistency of weaving process', $command->getHelp());
    }

    public function testFailsForInvalidLoaderPath(): void
    {
        $tester = new CommandTester(new DebugWeavingCommand());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid loader path');

        $tester->execute(['loader' => '/path/to/missing/loader.php']);
    }

    public function testRequiresLoaderArgument(): void
    {
        $tester = new CommandTester(new DebugWeavingCommand());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('loader');

        $tester->execute([]);
    }

    public function testBaseAspectCommandIsAbstract(): void
    {
        $this->assertTrue(new ReflectionClass(BaseAspectCommand::class)->isAbstract());
    }
}
