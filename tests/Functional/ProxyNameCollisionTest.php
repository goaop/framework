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

use Go\Tests\TestProject\Application\ImportCollisionClass;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Generated proxies keep short imports and alias them on collisions (issue #668): the woven class imports
 * a user class named `Interceptor` and is advised by two aspects sharing one short name.
 */
class ProxyNameCollisionTest extends BaseFunctionalTestCase
{
    public function testProxyWithCollidingShortNamesLoadsAndRunsBothAdvices(): void
    {
        $this->assertClassIsWoven(ImportCollisionClass::class);
        $this->assertMethodWoven(
            ImportCollisionClass::class,
            'write',
            'Go\\Tests\\TestProject\\Aspect\\Alpha\\CollisionAspect->beforeWrite',
        );
        $this->assertMethodWoven(
            ImportCollisionClass::class,
            'write',
            'Go\\Tests\\TestProject\\Aspect\\Beta\\CollisionAspect->beforeWrite',
        );

        // The warmup only generates the proxy, loading it is what fails with
        // "Cannot use ... because the name is already in use"
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $script = sprintf(
            'include %s; echo (new %s())->write("x");',
            var_export($this->configuration['frontController'], true),
            '\\' . ImportCollisionClass::class,
        );
        $process = new Process(
            [$phpExecutable, ...$this->getPhpOptions(), '-r', $script],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Loading the woven class failed: ' . $process->getOutput() . $process->getErrorOutput(),
        );
        $output = trim($process->getOutput());
        $this->assertStringContainsString('A;', $output);
        $this->assertStringContainsString('B;', $output);
        $this->assertStringEndsWith('log:x', $output);
    }

    protected function getConfigurationName(): string
    {
        return 'default';
    }
}
