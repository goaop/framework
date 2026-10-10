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

namespace Go\Instrument\ZEngine\InProcess;

use Go\Aop\WeavingDriver;
use Go\Core\AspectKernel;
use Go\Instrument\ZEngine\DonorCacheIndex;
use Go\PhpUnit\UsesTemporaryDirectory;
use Go\Stubs\ZEngine\InProcessAspect;
use Go\Stubs\ZEngine\InProcessKernel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use Throwable;
use ZEngine\Core;

/**
 * Base of the tests running the z-engine driver INSIDE the test runner: the engine is booted in this process and
 * the stub classes of tests/Stubs/ZEngine are woven for real, which is what gives the driver code coverage
 *
 * Every test boots its own kernel against a temporary cache directory. The kernel is created without the
 * per-process singleton (other tests of the process may hold a kernel of another class there) and installed as
 * the singleton only for the duration of the test, since woven code resolves its aspects through
 * AspectKernel::getInstance(); the previous singleton and the autoloaders of the test runner (the kernel wraps
 * composer's loader) are restored afterwards. A woven stub class stays woven for the rest of the process, so every
 * test uses stub classes of its own. The tests self-skip where the engine cannot run in this process: z-engine
 * not installed, FFI disabled, the opcache JIT active, or a z-engine branch of another PHP minor.
 */
abstract class ZEngineInProcessTestCase extends TestCase
{
    use UsesTemporaryDirectory;

    protected const string STUBS_DIR = __DIR__ . '/../../../Stubs/ZEngine';

    private static ?string $engineUnavailableReason = null;

    private static bool $engineChecked = false;

    /**
     * Autoloaders of the test runner, restored in tearDown()
     *
     * @var list<callable>
     */
    private array $originalLoaders = [];

    protected string $cacheDir;

    protected ?InProcessKernel $kernel = null;

    /**
     * Kernel singleton of the process before the test, restored in tearDown()
     */
    private ?AspectKernel $previousSingleton = null;

    protected function setUp(): void
    {
        $reason = self::engineUnavailableReason();
        if ($reason !== null) {
            self::markTestSkipped($reason);
        }
        $this->originalLoaders = spl_autoload_functions();
        $this->cacheDir        = self::createTemporaryDirectory('zengine-in-process');
        InProcessAspect::$log  = [];
    }

    protected function tearDown(): void
    {
        foreach (spl_autoload_functions() as $loader) {
            spl_autoload_unregister($loader);
        }
        foreach ($this->originalLoaders as $loader) {
            spl_autoload_register($loader);
        }
        if ($this->kernel !== null) {
            $this->kernel->getContainer()->getService(DonorCacheIndex::class)->flush();
            self::singletonProperty()->setValue(null, $this->previousSingleton);
            $this->kernel = null;
        }
        self::removeTemporaryDirectory($this->cacheDir);
    }

    /**
     * Boots a kernel for the zengine driver weaving the stub classes of tests/Stubs/ZEngine, and makes it the
     * kernel singleton of the process until tearDown()
     */
    protected function bootKernel(): InProcessKernel
    {
        $appDir = realpath(__DIR__ . '/../../../..');
        self::assertNotFalse($appDir);
        $stubsDir = realpath(self::STUBS_DIR);
        self::assertNotFalse($stubsDir);

        $kernel = new ReflectionClass(InProcessKernel::class)->newInstanceWithoutConstructor();
        $kernel->init([
            'driver'       => WeavingDriver::ZEngine,
            'debug'        => true,
            'appDir'       => $appDir,
            'cacheDir'     => $this->cacheDir,
            'includePaths' => [$stubsDir],
        ]);
        $singleton = self::singletonProperty();
        $previous  = $singleton->getValue();
        $this->previousSingleton = $previous instanceof AspectKernel ? $previous : null;
        $singleton->setValue(null, $kernel);

        return $this->kernel = $kernel;
    }

    private static function singletonProperty(): ReflectionProperty
    {
        return new ReflectionProperty(AspectKernel::class, 'instance');
    }

    /**
     * Why the engine cannot run in this process, null when it can (checked once per process, booting z-engine here)
     */
    private static function engineUnavailableReason(): ?string
    {
        if (self::$engineChecked) {
            return self::$engineUnavailableReason;
        }
        self::$engineChecked = true;
        if (!class_exists(Core::class)) {
            return self::$engineUnavailableReason = 'lisachenko/z-engine is not installed: '
                . 'composer require --dev lisachenko/z-engine:<branch of this PHP minor> (see docs/zengine-driver.md)';
        }
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        if (is_array($status) && is_array($status['jit'] ?? null) && ($status['jit']['on'] ?? false) === true) {
            return self::$engineUnavailableReason = 'The opcache JIT is active in the test runner: z-engine needs it off, '
                . 'start PHP with opcache.jit=off and opcache.jit_buffer_size=0';
        }
        try {
            Core::init();
        } catch (Throwable $failure) {
            return self::$engineUnavailableReason = 'z-engine cannot boot in this process (ext-ffi, ffi.enable=1 and the '
                . 'z-engine branch of this PHP minor are required): ' . $failure->getMessage();
        }

        return self::$engineUnavailableReason = null;
    }
}
