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

namespace Go\Instrument\ClassLoading\Opcache;

use Go\Tests\TestProject\Application\EdgeCaseDemo;
use Go\Tests\TestProject\Application\Greeter;
use Go\Tests\TestProject\Application\Main;
use Go\VirtualFileSystem\FileSystem;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The generated script is read as text here; that PHP preloads it is covered by the functional PreloadTest
 */
class PreloadScriptGeneratorTest extends TestCase
{
    private FileSystem $fileSystem;

    private string $appDir;

    private string $cacheDir;

    protected function setUp(): void
    {
        $this->fileSystem = FileSystem::mount('preloadvfs');
        $this->appDir     = $this->fileSystem->path('/app');
        $this->cacheDir   = $this->appDir . '/var/cache/aop';
        mkdir($this->cacheDir . '/src/Service', 0777, true);
        mkdir($this->appDir . '/vendor');

        $this->writeWovenClass('/src/Service/Order.php');
        $this->writeWovenClass('/src/Service/Stock.php', "class Stock {}\nStock::__staticInitialization();");
    }

    protected function tearDown(): void
    {
        $this->fileSystem->unmount();
    }

    public function testScriptLocatesEverythingThroughDir(): void
    {
        $script = $this->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringContainsString("\$loader = require __DIR__ . '/../../../vendor/autoload.php';", $script);
        $this->assertStringContainsString("})(__DIR__ . '/../../../vendor/composer/autoload_files.php');", $script);
        $this->assertStringContainsString("define('AOP_ROOT_DIR', __DIR__ . '/../../..')", $script);
        $this->assertStringContainsString("define('AOP_CACHE_DIR', __DIR__)", $script);
        $this->assertStringContainsString("'Go\\\\Tests\\\\TestProject\\\\Application\\\\EdgeCaseDemo' => __DIR__ . '/src/Service/OrderOriginalTrait.php',", $script);
        $this->assertStringNotContainsString($this->appDir, $script, 'No absolute path may be left in the script');
    }

    public function testScriptOutsideTheCacheDirectoryIsRelativeToItsOwnDirectory(): void
    {
        $script = $this->generate($this->appDir . '/config/preload.php');

        $this->assertStringContainsString("\$loader = require __DIR__ . '/../vendor/autoload.php';", $script);
        $this->assertStringContainsString("define('AOP_ROOT_DIR', __DIR__ . '/..')", $script);
        $this->assertStringContainsString("define('AOP_CACHE_DIR', __DIR__ . '/../var/cache/aop')", $script);
        $this->assertStringContainsString("'Go\\\\Tests\\\\TestProject\\\\Application\\\\EdgeCaseDemo' => __DIR__ . '/../var/cache/aop/src/Service/OrderOriginalTrait.php',", $script);
    }

    public function testWovenAndUntransformedClassesArePreloaded(): void
    {
        $script = $this->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringContainsString("\n    'Go\\\\Tests\\\\TestProject\\\\Application\\\\EdgeCaseDemo',\n", $script);
        $this->assertStringContainsString("\n    'Go\\\\Tests\\\\TestProject\\\\Application\\\\Greeter',\n", $script);
        $this->assertStringContainsString('class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);', $script);
    }

    /**
     * The static initialization advice of a class runs through the kernel when the class is loaded, which a
     * preloading process has not: such a class stays in the class map but is not loaded
     */
    public function testClassesWithStaticInitializationAdviceAreNotPreloaded(): void
    {
        $script = $this->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringContainsString("'Go\\\\Tests\\\\TestProject\\\\Application\\\\Main' => __DIR__ . '/src/Service/StockOriginalTrait.php',", $script);
        $this->assertStringNotContainsString("\n    'Go\\\\Tests\\\\TestProject\\\\Application\\\\Main',\n", $script);
        $this->assertStringContainsString("// Not preloaded, their static initialization advice runs with the kernel of a request:\n//   Go\\Tests\\TestProject\\Application\\Main\n", $script);
    }

    /**
     * A woven file that is not there (the cache was pruned by hand) can not be inspected: the class is preloaded like
     * any other and fails at preload time at worst, which the script tolerates
     */
    public function testUnreadableWovenFileIsPreloadedNevertheless(): void
    {
        $generator = new PreloadScriptGenerator(
            $this->cacheDir,
            $this->appDir,
            [Greeter::class => $this->cacheDir . '/src/Missing/GreeterOriginalTrait.php'],
            [],
            $this->appDir . '/vendor/autoload.php',
        );

        $script = $generator->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringContainsString("\n    'Go\\\\Tests\\\\TestProject\\\\Application\\\\Greeter',\n", $script);
        $this->assertStringNotContainsString('Not preloaded', $script);
    }

    public function testGeneratedScriptIsValidPhp(): void
    {
        $script = $this->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $script);
        // Throws a ParseError when the generated code does not compile
        $this->assertGreaterThan(100, count(token_get_all($script, TOKEN_PARSE)));
    }

    /**
     * Composer's `files` autoloads are marked as included before the autoloader is required: opcache can not link an
     * anonymous class of a helper function while preloading and warns about it on every start (Laravel's helpers)
     *
     * The script runs in a process of its own: it defines AOP_ROOT_DIR and AOP_CACHE_DIR and marks the files in
     * $GLOBALS['__composer_autoload_files'], which must not leak into other tests.
     */
    #[RunInSeparateProcess]
    public function testScriptDoesNotIncludeTheFilesAutoloadsOfComposer(): void
    {
        mkdir($this->appDir . '/vendor/composer');
        // Requires the `files` autoloads the way the autoload_real.php of Composer does
        file_put_contents($this->appDir . '/vendor/autoload.php', <<<'PHP'
            <?php
            foreach (require __DIR__ . '/composer/autoload_files.php' as $fileIdentifier => $file) {
                if (empty($GLOBALS['__composer_autoload_files'][$fileIdentifier])) {
                    $GLOBALS['__composer_autoload_files'][$fileIdentifier] = true;
                    require $file;
                }
            }
            echo "class loader registered\n";
            return new class {
                public function addClassMap(array $classMap): void {}
            };
            PHP);
        file_put_contents($this->appDir . '/vendor/composer/autoload_files.php', <<<'PHP'
            <?php
            // An identifier of its own: the autoloader of this process has marked the real ones as included already
            $vendorDir = dirname(__DIR__);
            return ['go-aop-preload-test-helpers' => $vendorDir . '/helpers.php'];
            PHP);
        file_put_contents($this->appDir . '/vendor/helpers.php', "<?php\necho \"helpers included\\n\";\n");
        $scriptPath = PreloadScriptGenerator::defaultScriptPath($this->cacheDir);
        $generator  = new PreloadScriptGenerator($this->cacheDir, $this->appDir, [], [], $this->appDir . '/vendor/autoload.php');
        file_put_contents($scriptPath, $generator->generate($scriptPath));

        $this->expectOutputString("class loader registered\n");

        include $scriptPath;
    }

    public function testPathWithoutCommonRootStaysAbsolute(): void
    {
        $generator = new PreloadScriptGenerator(
            $this->cacheDir,
            $this->appDir,
            [],
            [],
            'other://vendor/autoload.php',
        );

        $script = $generator->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringContainsString("\$loader = require 'other://vendor/autoload.php';", $script);
        $this->assertStringContainsString("})('other://vendor/composer/autoload_files.php');", $script);
    }

    private function generate(string $scriptPath): string
    {
        $generator = new PreloadScriptGenerator(
            $this->cacheDir,
            $this->appDir,
            [
                Main::class         => $this->cacheDir . '/src/Service/StockOriginalTrait.php',
                EdgeCaseDemo::class => $this->cacheDir . '/src/Service/OrderOriginalTrait.php',
            ],
            [Greeter::class => true],
            $this->appDir . '/vendor/autoload.php',
        );

        return $generator->generate($scriptPath);
    }

    /**
     * Writes a woven file and the proxy it includes, like the weaver lays them out in the cache
     */
    private function writeWovenClass(string $sourcePath, string $proxyBody = ''): void
    {
        $wovenFile = $this->cacheDir . substr($sourcePath, 0, -4) . 'OriginalTrait.php';
        $proxyFile = $this->cacheDir . $sourcePath;
        $className = basename($sourcePath, '.php');

        file_put_contents($wovenFile, "<?php\ntrait {$className}OriginalTrait {}\ninclude_once AOP_CACHE_DIR . '{$sourcePath}';\n");
        file_put_contents($proxyFile, "<?php\n" . ($proxyBody !== '' ? $proxyBody : "class {$className} {}") . "\n");
    }
}
