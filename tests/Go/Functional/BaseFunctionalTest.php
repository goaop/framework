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

use Go\Core\AspectContainer;
use Go\Instrument\PathResolver;
use Go\PhpUnit\ClassIsNotWovenConstraint;
use Go\PhpUnit\ClassWovenConstraint;
use Go\PhpUnit\ClassAdvisorIdentifier;
use Go\PhpUnit\ClassMemberWovenConstraint;
use Go\PhpUnit\ClassMemberNotWovenConstraint;
use PHPUnit_Framework_TestCase as TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
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
        // We need to normalize path to prevent Windows 260-length filename trouble
        $absoluteCacheDir = PathResolver::realpath($this->configuration['cacheDir']);
        if ($filesystem->exists($absoluteCacheDir)) {
            $filesystem->remove($absoluteCacheDir);
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
        $phpExecutable    = (new PhpExecutableFinder())->find();
        $commandStatement = sprintf('%s %s --no-ansi %s %s %s',
            $phpExecutable,
            $this->configuration['console'],
            $command,
            $this->configuration['frontController'],
            (null !== $args) ? $args : ''
        );

        $process = new Process($commandStatement, null, ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()]);
        $process->inheritEnvironmentVariables();
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
        $constraint = new ClassWovenConstraint($this->configuration);

        self::assertThat($class, $constraint, $message);
    }

    /**
     * Assert that class is not woven.
     *
     * @param string $class Full qualified class name which should not be subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsNotWoven($class, $message = '')
    {
        $constraint = new ClassIsNotWovenConstraint($this->configuration);

        self::assertThat($class, $constraint, $message);
    }

    /**
     * Assert that class static method is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $staticMethodName Name of static method.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL if any.
     * @param null|int $index Index of advisor identifier, or null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertStaticMethodWoven($class, $staticMethodName, $advisorIdentifier = null, $index = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $staticMethodName,
            AspectContainer::STATIC_METHOD_PREFIX,
            $advisorIdentifier,
            $index
        );
        $constraint = new ClassMemberWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class static method is not woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $staticMethodName Name of static method.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or NULL none should be registered.
     * @param string $message Assertion info message.
     */
    protected function assertStaticMethodNotWoven($class, $staticMethodName, $advisorIdentifier = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $staticMethodName,
            AspectContainer::STATIC_METHOD_PREFIX,
            $advisorIdentifier
        );
        $constraint = new ClassMemberNotWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class method is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $methodName Name of method.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL if any.
     * @param null|int $index Index of advisor identifier, or null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertMethodWoven($class, $methodName, $advisorIdentifier = null, $index = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $methodName,
            AspectContainer::METHOD_PREFIX,
            $advisorIdentifier,
            $index
        );
        $constraint = new ClassMemberWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class method is not woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $methodName Name of method.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or NULL if none should be registered.
     * @param string $message Assertion info message.
     */
    protected function assertMethodNotWoven($class, $methodName, $advisorIdentifier = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $methodName,
            AspectContainer::METHOD_PREFIX,
            $advisorIdentifier
        );
        $constraint = new ClassMemberNotWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class property is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $propertyName Property name.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL if any.
     * @param null|int $index Index of advisor identifier, or null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertPropertyWoven($class, $propertyName, $advisorIdentifier = null, $index = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $propertyName,
            AspectContainer::PROPERTY_PREFIX,
            $advisorIdentifier,
            $index
        );
        $constraint = new ClassMemberWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class property is not woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param string $propertyName Property name.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or NULL if none should be registered.
     * @param string $message Assertion info message.
     */
    protected function assertPropertyNotWoven($class, $propertyName, $advisorIdentifier = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            $propertyName,
            $advisorIdentifier,
            AspectContainer::PROPERTY_PREFIX
        );
        $constraint = new ClassMemberNotWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class initialization is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL if any.
     * @param null|int $index Index of advisor identifier, or null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertClassInitializationWoven($class, $advisorIdentifier = null, $index = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            'root',
            AspectContainer::INIT_PREFIX,
            $advisorIdentifier,
            $index
        );
        $constraint = new ClassMemberWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class initialization is not woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or NULL if none should be registered.
     * @param string $message Assertion info message.
     */
    protected function assertClassInitializationNotWoven($class, $advisorIdentifier = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            'root',
            AspectContainer::INIT_PREFIX,
            $advisorIdentifier
        );
        $constraint = new ClassMemberNotWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class static initialization is woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL if any.
     * @param null|int $index Index of advisor identifier, or null if order is not important.
     * @param string $message Assertion info message.
     */
    protected function assertClassStaticInitializationWoven($class, $advisorIdentifier = null, $index = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            'root',
            AspectContainer::STATIC_INIT_PREFIX,
            $advisorIdentifier,
            $index
        );
        $constraint = new ClassMemberWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }

    /**
     * Assert that class static initialization is not woven.
     *
     * @param string $class Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or NULL if none should be registered.
     * @param string $message Assertion info message.
     */
    protected function assertClassStaticInitializationNotWoven($class, $advisorIdentifier = null, $message = '')
    {
        $identifier = new ClassAdvisorIdentifier(
            $class,
            'root',
            AspectContainer::STATIC_INIT_PREFIX,
            $advisorIdentifier
        );
        $constraint = new ClassMemberNotWovenConstraint($this->configuration);

        self::assertThat($identifier, $constraint, $message);
    }
}
