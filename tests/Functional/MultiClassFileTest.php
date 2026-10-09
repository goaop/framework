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

use Go\Tests\TestProject\Application\MultiClassHolder;
use Go\Tests\TestProject\Application\MultiClassPlain;
use Go\Tests\TestProject\Application\MultiClassSecond;
use Go\Tests\TestProject\Application\Sub\MultiClassThird;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Several woven classes declared in one file, in two namespace blocks, share one proxy file (issue #760): every
 * class is declared once the file is loaded and runs its advice, the unwoven class of the file stays as it is
 */
class MultiClassFileTest extends BaseFunctionalTestCase
{
    public function testAllWovenClassesOfFileAreDeclaredAndAdvised(): void
    {
        $this->assertClassIsWoven(MultiClassHolder::class);

        $output = $this->runScript(
            $this->getFirstLoadedClass(),
            sprintf(
                'echo (new \\%s())->hello(), "|", (new \\%s())->hello(), "|", (new \\%s())->hello(), "|", (new \\%s())->greet();',
                MultiClassHolder::class,
                MultiClassSecond::class,
                MultiClassThird::class,
                MultiClassPlain::class,
            ),
        );

        $this->assertSame('advised:holder|advised:second:plain|advised:third|plain', $output);
    }

    /**
     * Class loaded first: in debug mode composer locates the classes, so it is the one named after the file
     */
    protected function getFirstLoadedClass(): string
    {
        return MultiClassHolder::class;
    }

    private function runScript(string $firstLoadedClass, string $code): string
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $script = sprintf(
            'include %s; new \\%s(); %s',
            var_export($this->configuration['frontController'], true),
            $firstLoadedClass,
            $code,
        );
        $process = new Process(
            [$phpExecutable, '-r', $script],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'Loading the woven classes failed: ' . $process->getOutput() . $process->getErrorOutput(),
        );

        return trim($process->getOutput());
    }

    protected function getConfigurationName(): string
    {
        return 'default';
    }
}
