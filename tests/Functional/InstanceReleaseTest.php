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
 * Joinpoints live for the whole process, so they must not keep the first instance calling a woven method alive (#673)
 */
class InstanceReleaseTest extends BaseFunctionalTestCase
{
    public function testInstancesAreReleasedAfterCallingWovenMethods(): void
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, __DIR__ . '/../Fixtures/project/bin/instance-release.php'],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $this->assertSame(
            ['own method' => true, 'trait method' => true, 'main' => true],
            json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR),
        );
    }
}
