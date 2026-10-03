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

namespace Go\Functional;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Magic constants of unwoven files resolve to their original location on every request (#679)
 *
 * The included file is served through the stream filter (always in debug mode, and in production because
 * it has no cache file) with its original source on cache hits: PHP resolves the magic constants of a
 * `php://filter/.../resource=<path>` include to <path>, so they need no rewriting.
 */
class MagicConstantTest extends BaseFunctionalTestCase
{
    public function testMagicConstantsResolveToOriginalLocationOnEveryRequest(): void
    {
        $applicationDir = realpath(__DIR__ . '/../Fixtures/project/src/Application');
        $this->assertIsString($applicationDir);
        $expected = [
            'holder'   => ['dir' => $applicationDir, 'file' => $applicationDir . DIRECTORY_SEPARATOR . 'MagicConstantHolder.php'],
            'included' => ['dir' => $applicationDir, 'file' => $applicationDir . DIRECTORY_SEPARATOR . 'magic-constant-paths.php'],
        ];

        // The cache was warmed up in setUp(): both requests are served from the cache
        $this->assertSame($expected, $this->runScript());
        $this->assertSame($expected, $this->runScript());
    }

    /**
     * @return array<mixed>
     */
    private function runScript(): array
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, __DIR__ . '/../Fixtures/project/bin/magic-constants.php'],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);

        return $result;
    }
}
