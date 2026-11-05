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

use Go\Instrument\ClassLoading\Opcache\PreloadScriptGenerator;
use Go\Tests\TestProject\Application\EdgeCaseDemo;
use Go\Tests\TestProject\Application\Main;
use Go\Tests\TestProject\Aspect\EdgeCaseAspect;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The opcache.preload script written by `cache:warmup:aop --preload` links the woven classes when PHP starts
 *
 * Runs the fixture project in a PHP process started with the script as opcache.preload: the woven classes behave as
 * without preloading, and the request includes fewer files.
 */
class PreloadTest extends BaseFunctionalTestCase
{
    private string $movedCacheDir;

    public function setUp(): void
    {
        // The base tearDown() clears the cache directory of the configuration, also after a skip
        $this->loadConfiguration();
        if (!function_exists('opcache_get_status')) {
            $this->markTestSkipped('Preloading needs the opcache extension');
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('PHP does not support opcache.preload on Windows');
        }
        parent::setUp();
        $this->movedCacheDir = $this->configuration['cacheDir'] . '-moved';
        (new Filesystem())->remove($this->movedCacheDir);
    }

    public function tearDown(): void
    {
        if (isset($this->movedCacheDir)) {
            (new Filesystem())->remove($this->movedCacheDir);
        }
        parent::tearDown();
    }

    protected function warmUp(): void
    {
        $output = $this->execute('cache:warmup:aop', ['--preload']);

        $this->assertStringContainsString('Preload script written to', $output);
    }

    public function testPreloadedClassesBehaveLikeAutoloadedOnes(): void
    {
        $preloadScript = PreloadScriptGenerator::defaultScriptPath($this->configuration['cacheDir']);
        $this->assertFileExists($preloadScript);

        $withoutPreload = $this->runProject('production', null);
        $withPreload    = $this->runProject('production', $preloadScript);

        $this->assertSame($withoutPreload['result'], $withPreload['result'], 'Advices run the same with preloaded classes');
        $this->assertSame('1,7,3|1', $withPreload['result']);
        $this->assertLessThan($withoutPreload['includedFiles'], $withPreload['includedFiles'], 'Preloaded classes are not included again');
        $this->assertFalse($withPreload['wovenFileIncluded'], 'The woven file of a preloaded class is linked already');
        // Main has a static initialization advice: it is left to the autoloader and still works
        $this->assertTrue($withPreload['mainLoaded']);
        $this->assertTrue($withPreload['mainIncluded'], 'The class with a static initialization advice is loaded by the request');
        $this->assertSame('', $withPreload['errorOutput'], 'No class fails to preload');
    }

    /**
     * The script holds no absolute path: the cache can be moved together with its script
     */
    public function testMovedCacheIsPreloadedFromItsNewLocation(): void
    {
        (new Filesystem())->mirror($this->configuration['cacheDir'], $this->movedCacheDir);
        $this->clearCache();

        $run = $this->runProject('moved_cache', PreloadScriptGenerator::defaultScriptPath($this->movedCacheDir));

        $this->assertSame('1,7,3|1', $run['result']);
        $this->assertFalse($run['wovenFileIncluded'], 'The woven class was preloaded from the moved cache');
        $this->assertSame('', $run['errorOutput']);
        $this->assertDirectoryDoesNotExist($this->configuration['cacheDir'], 'Nothing may be woven again');
    }

    /**
     * Runs the fixture project with the given configuration, preloading the given script
     *
     * @return array{result: string, includedFiles: int, wovenFileIncluded: bool, mainLoaded: bool, mainIncluded: bool, errorOutput: string}
     */
    private function runProject(string $configuration, ?string $preloadScript): array
    {
        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);

        $script = sprintf(
            <<<'PHP'
                include %s;
                $result = (new %s())->describe(second: 7) . '|' . %s::$calls;
                $included = get_included_files();
                $isIncluded = static fn(string $suffix): bool => array_any($included, static fn(string $file): bool => str_ends_with($file, $suffix));
                echo json_encode([
                    'result'            => $result,
                    'includedFiles'     => count($included),
                    'wovenFileIncluded' => $isIncluded('/EdgeCaseDemoOriginalTrait.php'),
                    'mainLoaded'        => class_exists(%s),
                    'mainIncluded'      => array_any(get_included_files(), static fn(string $file): bool => str_ends_with($file, '/MainOriginalTrait.php')),
                ], JSON_THROW_ON_ERROR);
                PHP,
            var_export($this->configuration['frontController'], true),
            '\\' . EdgeCaseDemo::class,
            '\\' . EdgeCaseAspect::class,
            var_export(Main::class, true),
        );

        $commandLine = [$phpExecutable, '-d', 'opcache.enable_cli=1', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0'];
        if ($preloadScript !== null) {
            $commandLine = [...$commandLine, '-d', 'opcache.preload=' . $preloadScript];
            // PHP refuses to preload as root without a user to run the script as
            if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
                $commandLine = [...$commandLine, '-d', 'opcache.preload_user=root'];
            }
        }
        $process = new Process([...$commandLine, '-r', $script], null, ['GO_AOP_CONFIGURATION' => $configuration]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        $run = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($run);
        $run['errorOutput'] = $process->getErrorOutput();

        /** @var array{result: string, includedFiles: int, wovenFileIncluded: bool, mainLoaded: bool, mainIncluded: bool, errorOutput: string} $run */
        return $run;
    }

    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
