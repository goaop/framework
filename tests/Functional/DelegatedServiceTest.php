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

use Go\Tests\TestProject\Application\Greeter;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * An aspect whose dependency comes from the application container is woven without that container (cache
 * warm-up runs before it exists): its factory resolves the dependency through the delegate on first use
 */
class DelegatedServiceTest extends BaseFunctionalTestCase
{
    public function testAspectFromApplicationContainerIsApplied(): void
    {
        $this->assertMethodWoven(Greeter::class, 'greet', 'Go\\Tests\\TestProject\\Aspect\\GreetingAspect->aroundGreet');

        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, __DIR__ . '/../Fixtures/project/bin/delegated-aspect.php'],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['greeting' => 'Hello, World!', 'delegated' => true], $result);
    }

    protected function getConfigurationName(): string
    {
        return 'default';
    }
}
