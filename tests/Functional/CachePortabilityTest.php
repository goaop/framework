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

use Go\Tests\TestProject\Application\EdgeCaseDemo;
use Go\Tests\TestProject\Aspect\EdgeCaseAspect;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A warmed cache holds no absolute paths: it keeps working after being moved to another directory
 */
class CachePortabilityTest extends BaseFunctionalTestCase
{
    private string $movedCacheDir;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();
        $this->movedCacheDir = $this->configuration['cacheDir'] . '-moved';
        (new Filesystem())->remove($this->movedCacheDir);
    }

    #[\Override]
    public function tearDown(): void
    {
        (new Filesystem())->remove($this->movedCacheDir);
        parent::tearDown();
    }

    public function testMovedCacheIsServedAsPrebuiltCache(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mirror($this->configuration['cacheDir'], $this->movedCacheDir);
        // Without the original cache only the moved copy can serve the woven classes
        $this->clearCache();

        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);
        $script = sprintf(
            'include %s; echo (new %s())->describe(second: 7), "|", %s::$calls;',
            var_export($this->configuration['frontController'], true),
            '\\' . EdgeCaseDemo::class,
            '\\' . EdgeCaseAspect::class,
        );
        $process = new Process([$phpExecutable, '-r', $script], null, ['GO_AOP_CONFIGURATION' => 'moved_cache']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        // The advice ran, so the woven class was loaded from the moved cache
        $this->assertSame('1,7,3|1', trim($process->getOutput()));
        $this->assertDirectoryDoesNotExist($this->configuration['cacheDir'], 'Nothing may be woven again');
    }

    #[\Override]
    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
