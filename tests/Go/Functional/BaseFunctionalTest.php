<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2011, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Go\Functional;

use Go\PhpUnit\ClassIsNotWovenConstraint;
use Go\PhpUnit\ClassWovenConstraint;
use Go\PhpUnit\JoinPoint;
use Go\PhpUnit\JoinPointExistsConstraint;
use Go\PhpUnit\JoinPointNotExistsConstraint;
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
            $configurations      = require __DIR__ . '/../../Fixtures/project/web/configuration.php';
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
        self::assertThat($class, new ClassWovenConstraint($this->configuration), $message);
    }

    /**
     * Assert that class is not woven.
     *
     * @param string $class Full qualified class name which is not subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsNotWoven($class, $message = '')
    {
        self::assertThat($class, new ClassIsNotWovenConstraint($this->configuration), $message);
    }

    /**
     * Assert that class has static method join point.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $staticMethodName Name of static method.
     * @param string $joinPoint Join point expression.
     * @param null|int $index Index of join point expression, od null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertStaticMethodJoinPointExists($class, $staticMethodName, $joinPoint, $index = null, $message = '')
    {
        self::assertThat(new JoinPoint($class, $staticMethodName, $joinPoint, true, $index), new JoinPointExistsConstraint($this->configuration), $message);
    }

    /**
     * Assert that class does not have static method join point.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $staticMethodName Name of static method.
     * @param string $joinPoint Join point expression.
     * @param string $message Assertion info message.
     */
    protected function assertStaticMethodJoinPointNotExists($class, $staticMethodName, $joinPoint, $message = '')
    {
        self::assertThat(new JoinPoint($class, $staticMethodName, $joinPoint, true), new JoinPointNotExistsConstraint($this->configuration), $message);
    }

    /**
     * Assert that class has method join point.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $methodName Name of method.
     * @param string $joinPoint Join point expression.
     * @param null|int $index Index of join point expression, od null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertMethodJoinPointExists($class, $methodName, $joinPoint, $index = null, $message = '')
    {
        self::assertThat(new JoinPoint($class, $methodName, $joinPoint, false, $index), new JoinPointExistsConstraint($this->configuration), $message);
    }

    /**
     * Assert that class does not have method join point.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $methodName Name of method.
     * @param string $joinPoint Join point expression.
     * @param string $message Assertion info message.
     */
    protected function assertMethodJoinPointNotExists($class, $methodName, $joinPoint, $message = '')
    {
        self::assertThat(new JoinPoint($class, $methodName, $joinPoint, false), new JoinPointNotExistsConstraint($this->configuration), $message);
    }
}
