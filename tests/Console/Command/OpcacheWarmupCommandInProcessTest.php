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

namespace Go\Console\Command;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * In-process tests for the cache:warmup:opcache command: the PHPUnit process has no opcache file cache, so the
 * command stops at its configuration check here; priming itself is covered by the functional OpcacheWarmupCommandTest
 */
class OpcacheWarmupCommandInProcessTest extends TestCase
{
    public function testMetadataIsDefinedByAttribute(): void
    {
        $command = new OpcacheWarmupCommand();

        $this->assertSame('cache:warmup:opcache', $command->getName());
        $this->assertSame('Prime the opcache file cache with the AOP cache and the application', $command->getDescription());
        $this->assertStringContainsString('opcache.file_cache_only=1', $command->getHelp());
    }

    public function testFailsWithTheCommandLineToRunWhenTheProcessHasNoFileCache(): void
    {
        $tester   = new CommandTester(new OpcacheWarmupCommand());
        $exitCode = $tester->execute(['loader' => __DIR__ . '/../../../vendor/autoload.php', '--file-cache' => '/var/cache/opcache']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Can not prime the opcache file cache: ', $tester->getDisplay());
        $this->assertStringContainsString('-d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache=/var/cache/opcache', $tester->getDisplay());
    }

    public function testFileCacheOptionMustBeAPath(): void
    {
        try {
            (new CommandTester(new OpcacheWarmupCommand()))->execute([
                'loader'       => __DIR__ . '/../../../vendor/autoload.php',
                '--file-cache' => ['not', 'a', 'path'],
            ]);
            $this->fail('An array is no file cache directory');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('--file-cache option must be a path', $exception->getMessage());
        }
    }

    public function testEntryPointsMustExist(): void
    {
        try {
            (new CommandTester(new OpcacheWarmupCommand()))->execute([
                'loader'  => __DIR__ . '/../../../vendor/autoload.php',
                '--entry' => [__FILE__, __DIR__ . '/missing/index.php'],
            ]);
            $this->fail('A missing entry point must be rejected');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Entry point ' . __DIR__ . '/missing/index.php does not exist', $exception->getMessage());
        }
    }
}
