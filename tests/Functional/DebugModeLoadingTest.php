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

use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Tests\TestProject\Application\MagicConstantHolder;
use Go\Tests\TestProject\Application\Main;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Debug mode includes a file known to the cache as untransformed by its original path, so opcache caches it, and
 * keeps php://filter for woven files and stale records; init() tracks the aspects without loading them (issue #745)
 */
class DebugModeLoadingTest extends BaseFunctionalTestCase
{
    private const string FIXTURE_DIR = __DIR__ . '/../Fixtures/project/src';

    public function testFreshUntransformedFileIsIncludedByItsOriginalPath(): void
    {
        $result = $this->runScript(MagicConstantHolder::class, Main::class);

        $this->assertSame([], $result['aspectsLoadedByInit'], 'init() must track the aspects without loading them');

        $original = realpath(self::FIXTURE_DIR . '/Application/MagicConstantHolder.php');
        $this->assertSame(
            ['file' => $original, 'loaded' => true, 'fileName' => $original],
            $result['classes'][MagicConstantHolder::class],
        );

        // Magic constants and breakpoints of a woven file rely on the filter in debug mode
        $this->assertSame(
            $this->filterPath(self::FIXTURE_DIR . '/Application/Main.php'),
            $result['classes'][Main::class]['file'],
        );
        $this->assertTrue($result['classes'][Main::class]['loaded']);
        $this->assertClassIsWoven(Main::class);
    }

    public function testChangedSourceGoesThroughTheFilter(): void
    {
        $this->withChangedMtime(self::FIXTURE_DIR . '/Application/MagicConstantHolder.php', function (): void {
            $result = $this->runScript(MagicConstantHolder::class);

            $this->assertSame(
                $this->filterPath(self::FIXTURE_DIR . '/Application/MagicConstantHolder.php'),
                $result['classes'][MagicConstantHolder::class]['file'],
            );
            $this->assertTrue($result['classes'][MagicConstantHolder::class]['loaded']);
        });
    }

    public function testChangedAspectSendsEveryFileThroughTheFilterAgain(): void
    {
        // The aspect is tracked by init() without being loaded: its change still invalidates the cache records
        $this->withChangedMtime(self::FIXTURE_DIR . '/Aspect/LoggingAspect.php', function (): void {
            $result = $this->runScript(MagicConstantHolder::class);

            $this->assertSame([], $result['aspectsLoadedByInit']);
            $this->assertSame(
                $this->filterPath(self::FIXTURE_DIR . '/Application/MagicConstantHolder.php'),
                $result['classes'][MagicConstantHolder::class]['file'],
            );
        });
        // Woven again on that request: the next one includes the file directly again
        $result   = $this->runScript(MagicConstantHolder::class);
        $original = realpath(self::FIXTURE_DIR . '/Application/MagicConstantHolder.php');
        $this->assertSame($original, $result['classes'][MagicConstantHolder::class]['file']);
    }

    /**
     * Runs the given callback while the file has a newer mtime than every cache record, restoring it afterward
     */
    private function withChangedMtime(string $file, callable $callback): void
    {
        $originalMtime = filemtime($file);
        $this->assertIsInt($originalMtime);
        touch($file, time() + 60);
        clearstatcache();
        try {
            $callback();
        } finally {
            touch($file, $originalMtime);
            clearstatcache();
        }
    }

    private function filterPath(string $file): string
    {
        return SourceTransformingLoader::PHP_FILTER_READ . SourceTransformingLoader::FILTER_IDENTIFIER
            . '/resource=' . realpath($file);
    }

    /**
     * @return array{aspectsLoadedByInit: list<string>, classes: array<string, array{file: string|false, loaded: bool, fileName: string|false}>}
     */
    private function runScript(string ...$classNames): array
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $this->assertIsString($phpExecutable);
        $process = new Process(
            [$phpExecutable, ...$this->getPhpOptions(), __DIR__ . '/../Fixtures/project/bin/debug-loading.php', ...$classNames],
            null,
            ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()],
        );
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());

        /** @var array{aspectsLoadedByInit: list<string>, classes: array<string, array{file: string|false, loaded: bool, fileName: string|false}>} $result Shape of our own fixture script is trusted */
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);

        return $result;
    }
}
