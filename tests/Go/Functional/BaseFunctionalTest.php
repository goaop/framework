<?php
declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\Instrument\PathResolver;
use Go\ParserReflection\ReflectionClass;
use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Base class for functional tests.
 */
abstract class BaseFunctionalTest extends TestCase
{
    /**
     * Configuration which ought to be used in this test suite.
     *
     * @var array
     */
    protected $configuration;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->loadConfiguration();
        $this->clearCache();
        $this->warmUp();
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        $this->clearCache();
    }

    /**
     * Clear Go! AOP cache.
     */
    protected function clearCache()
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($this->configuration['cacheDir'])) {
            $filesystem->remove($this->configuration['cacheDir']);
        }
    }

    /**
     * Warms up Go! AOP cache.
     *
     * @return string Command output.
     */
    protected function warmUp()
    {
        return $this->execute('cache:warmup:aop');
    }

    /**
     * Get configuration name.
     *
     * Get configuration name from available configurations settings defined in
     * /tests/Fixtures/project/web/configuration.php used for executing this
     * functional test suite.
     *
     * Override this method to use desired configuration settings.
     *
     * @return string
     */
    protected function getConfigurationName()
    {
        return 'default';
    }

    /**
     * Load configuration settings.
     *
     * Load configuration settings which ought to be used in this test suite,
     * defined in /tests/Fixtures/project/web/configuration.php.
     */
    protected function loadConfiguration()
    {
        if (!$this->configuration) {
            $configurations = require __DIR__.'/../../Fixtures/project/web/configuration.php';
            $this->configuration = $configurations[$this->getConfigurationName()];
        }
    }

    /**
     * Execute console command.
     *
     * @param string $command Command to execute.
     * @param string|null $args Command arguments to append, if any.
     * @param bool $expectSuccess Should command be executed successfully
     * @param null|int $expectedExitCode If provided, exit code will be asserted.
     *
     * @return string Console output.
     */
    protected function execute($command, $args = null, $expectSuccess = true, $expectedExitCode = null)
    {
        $commandStatement = sprintf('GO_AOP_CONFIGURATION=%s php %s %s %s %s',
            $this->getConfigurationName(),
            $this->configuration['console'],
            $command,
            $this->configuration['frontController'],
            (null !== $args) ? $args : ''
        );

        $process = new Process($commandStatement);

        $process->run();

        if ($expectSuccess) {
            $this->assertTrue($process->isSuccessful(), sprintf('Unable to execute "%s" command, got output: "%s".', $command, $process->getOutput()));
        } else {
            $this->assertFalse($process->isSuccessful(), sprintf('Command "%s" excuted successfully, even if it is expected to fail, got output: "%s".', $command, $process->getOutput()));
        }

        if (null !== $expectedExitCode) {
            $this->assertEquals($expectedExitCode, $process->getExitCode(), 'Assert that exit code is matched.');
        }

        return $process->getOutput();
    }

    /**
     * Assert that class is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsWoven($class, $message = '')
    {
        $filename = (new ReflectionClass($class))->getFileName();
        $suffix = substr($filename, strlen(PathResolver::realpath($this->configuration['appDir'])));

        $this->assertFileExists($this->configuration['cacheDir'].$suffix, $message);
        $this->assertFileExists($this->configuration['cacheDir'].'/_proxies'.$suffix, $message);
    }

    /**
     * Assert that class is not woven.
     *
     * @param string $class Full qualified class name which is not subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsNotWoven($class, $message = '')
    {
        $filename = (new ReflectionClass($class))->getFileName();
        $suffix = substr($filename, strlen(PathResolver::realpath($this->configuration['appDir'])));

        $this->assertFileNotExists($this->configuration['cacheDir'].$suffix, $message);
        $this->assertFileNotExists($this->configuration['cacheDir'].'/_proxies'.$suffix, $message);
    }
}
