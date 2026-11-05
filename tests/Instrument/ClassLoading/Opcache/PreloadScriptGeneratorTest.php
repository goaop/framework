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

    public function testGeneratedScriptIsValidPhp(): void
    {
        $script = $this->generate(PreloadScriptGenerator::defaultScriptPath($this->cacheDir));

        $this->assertStringStartsWith("<?php\n\ndeclare(strict_types=1);\n", $script);
        // Throws a ParseError when the generated code does not compile
        $this->assertGreaterThan(100, count(token_get_all($script, TOKEN_PARSE)));
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
