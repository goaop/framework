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

use Go\Aop\Exception\WeavingException;
use Go\Instrument\ClassLoading\SourceTransformingLoader;
use Go\Instrument\Transformer\StreamMetaData;
use Go\Instrument\Transformer\WeavingTransformer;
use Go\Tests\TestProject\Application\EdgeCaseDemo;
use Go\Tests\TestProject\Aspect\EdgeCaseAspect;
use PhpParser\Parser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * A warmed cache holds no absolute paths: it keeps working after being moved to another directory, as a prebuilt
 * cache that fails a request on a class it misses (#808)
 */
class CachePortabilityTest extends BaseFunctionalTestCase
{
    /**
     * Class the prebuilt cache misses, its file exists only while the test runs
     */
    private const string ADDED_CLASS = 'Go\\Tests\\TestProject\\Application\\AddedAfterTheBuild';

    private string $movedCacheDir;

    public function setUp(): void
    {
        parent::setUp();
        $this->movedCacheDir = $this->configuration['cacheDir'] . '-moved';
        (new Filesystem())->remove($this->movedCacheDir);
    }

    public function tearDown(): void
    {
        (new Filesystem())->remove([$this->movedCacheDir, self::addedClassFile()]);
        parent::tearDown();
    }

    /**
     * Source of the class the prebuilt cache misses: written by the test after the build, below the include paths
     */
    private static function addedClassFile(): string
    {
        return __DIR__ . '/../Fixtures/project/src/Application/AddedAfterTheBuild.php';
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

    public function testClassAddedAfterTheBuildFailsTheRequestWithoutWeavingOrWriting(): void
    {
        $filesystem = new Filesystem();
        $filesystem->mirror($this->configuration['cacheDir'], $this->movedCacheDir);
        $this->clearCache();
        file_put_contents(
            self::addedClassFile(),
            "<?php\ndeclare(strict_types=1);\n\nnamespace Go\\Tests\\TestProject\\Application;\n\nclass AddedAfterTheBuild {}\n",
        );
        $cacheFiles = self::snapshotDirectory($this->movedCacheDir);

        $phpExecutable = (new PhpExecutableFinder())->find();
        assert($phpExecutable !== false);
        // Nothing of the weaving pipeline may be loaded on the way to the failure
        $pipelineClasses = [SourceTransformingLoader::class, StreamMetaData::class, WeavingTransformer::class, Parser::class];
        $script = sprintf(
            'include %s; try { new %s(); echo "loaded"; } catch (%s $exception) { echo json_encode(['
            . '"message" => $exception->getMessage(), '
            . '"loaded" => array_values(array_filter(%s, static fn($name) => class_exists($name, false) || interface_exists($name, false))),'
            . ']); }',
            var_export($this->configuration['frontController'], true),
            '\\' . self::ADDED_CLASS,
            '\\' . WeavingException::class,
            var_export($pipelineClasses, true),
        );
        $process = new Process([$phpExecutable, '-r', $script], null, ['GO_AOP_CONFIGURATION' => 'moved_cache']);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getOutput() . $process->getErrorOutput());
        $this->assertSame('', $process->getErrorOutput(), 'No warning');
        $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($result);
        $message = $result['message'] ?? null;
        $this->assertIsString($message);
        $this->assertStringStartsWith('Class ' . self::ADDED_CLASS . ' (', $message);
        // Separator-agnostic: the resolved paths are native, the configured ones are not
        $message = str_replace('\\', '/', $message);
        $this->assertStringContainsString('/src/Application/AddedAfterTheBuild.php) is missing from the prebuilt AOP cache in ', $message);
        $this->assertStringContainsString('aspect-moved: with Features::PREBUILT_CACHE a request never weaves', $message);
        $this->assertStringEndsWith('rebuild it with `bin/aspect cache:warmup:aop`', $message);
        $this->assertSame([], $result['loaded'] ?? null, 'The request fails before any weaving work');
        $this->assertSame($cacheFiles, self::snapshotDirectory($this->movedCacheDir), 'The prebuilt cache is not written');
        $this->assertDirectoryDoesNotExist($this->configuration['cacheDir'], 'Nothing is woven elsewhere either');
    }

    /**
     * Content hash and modification time of every file below a directory, keyed by the path in one separator form
     *
     * @return array<string, string>
     */
    private static function snapshotDirectory(string $directory): array
    {
        clearstatcache();
        $snapshot = [];
        $files    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            assert($file instanceof \SplFileInfo);
            $snapshot[str_replace('\\', '/', $file->getPathname())] = hash_file('xxh128', $file->getPathname()) . '@' . $file->getMTime();
        }
        ksort($snapshot);

        return $snapshot;
    }

    protected function getConfigurationName(): string
    {
        return 'production';
    }
}
