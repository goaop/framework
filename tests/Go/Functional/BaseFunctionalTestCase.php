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

use Go\Core\AspectContainer;
use Go\Instrument\PathResolver;
use Go\PhpUnit\ClassAdvisorIdentifier;
use Go\PhpUnit\ClassIsNotWovenConstraint;
use Go\PhpUnit\ClassMemberNotWovenConstraint;
use Go\PhpUnit\ClassMemberWovenConstraint;
use Go\PhpUnit\ClassWovenConstraint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Base class for functional tests.
 */
abstract class BaseFunctionalTestCase extends TestCase
{
    /**
     * Configuration which ought to be used in this test suite.
     */
    protected array $configuration = [];

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        $this->loadConfiguration();
        $this->clearCache();
        $this->warmUp();
    }

    /**
     * {@inheritdoc}
     */
    public function tearDown(): void
    {
        $this->clearCache();
    }

    /**
     * Clear Go! AOP cache.
     */
    protected function clearCache(): void
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
     */
    protected function warmUp(): void
    {
        $this->execute('cache:warmup:aop');
    }

    /**
     * Get configuration name.
     *
     * Get configuration name from available configurations settings defined in
     * /tests/Fixtures/project/web/configuration.php used for executing this
     * functional test suite.
     *
     * Override this method to use desired configuration settings.
     */
    protected function getConfigurationName(): string
    {
        return 'default';
    }

    /**
     * Load configuration settings.
     *
     * Load configuration settings which ought to be used in this test suite,
     * defined in /tests/Fixtures/project/web/configuration.php.
     */
    protected function loadConfiguration(): void
    {
        if (!$this->configuration) {
            $configurations      = require __DIR__ . '/../../Fixtures/project/web/configuration.php';
            $this->configuration = $configurations[$this->getConfigurationName()];
        }
    }

    /**
     * Execute console command.
     *
     * @param string   $command          Command to execute.
     * @param array    $args             Optional command arguments to append, if any.
     * @param bool     $expectSuccess    Should command be executed successfully
     * @param null|int $expectedExitCode If provided, exit code will be asserted.
     *
     * @return string Console output.
     */
    protected function execute(
        string $command,
        array $args = [],
        bool $expectSuccess = true,
        ?int $expectedExitCode = null
    ): string {
        $phpExecutable = (new PhpExecutableFinder())->find();
        $commandLine   = [
            $phpExecutable,
            $this->configuration['console'],
            '--no-ansi',
            $command,
            $this->configuration['frontController'],
        ];

        $commandLine = array_merge($commandLine, $args);
        $process     = new Process($commandLine, null, ['GO_AOP_CONFIGURATION' => $this->getConfigurationName()]);
        $process->run();

        if ($expectSuccess) {
            $this->assertTrue(
                $process->isSuccessful(),
                sprintf(
                    'Unable to execute "%s" command, got output: "%s".',
                    $process->getCommandLine(),
                    $process->getOutput()
                )
            );
        } else {
            $this->assertFalse(
                $process->isSuccessful(),
                sprintf(
                    'Command "%s" executed successfully, even if it is expected to fail, got output: "%s".',
                    $command,
                    $process->getOutput()
                )
            );
        }

        if (null !== $expectedExitCode) {
            $this->assertEquals($expectedExitCode, $process->getExitCode(), 'Assert that exit code is matched.');
        }

        return $process->getOutput();
    }

    /**
     * Assert that class is woven.
     *
     * @param string $class   Full qualified class name which is subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsWoven(string $class, string $message = ''): void
    {
        $constraint = new ClassWovenConstraint($this->configuration);

        self::assertThat($class, $constraint, $message);
    }

    /**
     * Assert that class is not woven.
     *
     * @param string $class   Full qualified class name which should not be subject of weaving.
     * @param string $message Assertion info message.
     */
    protected function assertClassIsNotWoven(string $class, string $message = ''): void
    {
        $constraint = new ClassIsNotWovenConstraint($this->configuration);

        self::assertThat($class, $constraint, $message);
    }

    /**
     * Assert that class static method is woven.
     *
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $staticMethodName  Name of static method.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL
     *                                       if any.
     * @param null|int    $index             Index of advisor identifier, or null if order is not important.
     * @param string      $message           Assertion info message.
     */
    protected function assertStaticMethodWoven(
        string $class,
        string $staticMethodName,
        ?string $advisorIdentifier = null,
        ?int $index = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $staticMethodName  Name of static method.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or
     *                                       NULL none should be registered.
     * @param string      $message           Assertion info message.
     */
    protected function assertStaticMethodNotWoven(
        string $class,
        string $staticMethodName,
        ?string $advisorIdentifier = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $methodName        Name of method.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL
     *                                       if any.
     * @param null|int    $index             Index of advisor identifier, or null if order is not important.
     * @param string      $message           Assertion info message.
     */
    protected function assertMethodWoven(
        string $class,
        string $methodName,
        ?string $advisorIdentifier = null,
        ?int $index = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $methodName        Name of method.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or
     *                                       NULL if none should be registered.
     * @param string      $message           Assertion info message.
     */
    protected function assertMethodNotWoven(
        string $class,
        string $methodName,
        ?string $advisorIdentifier = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $propertyName      Property name.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL
     *                                       if any.
     * @param null|int    $index             Index of advisor identifier, or null if order is not important.
     * @param string      $message           Assertion info message.
     */
    protected function assertPropertyWoven(
        string $class,
        string $propertyName,
        ?string $advisorIdentifier = null,
        ?int $index = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param string      $propertyName      Property name.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or
     *                                       NULL if none should be registered.
     * @param string      $message           Assertion info message.
     */
    protected function assertPropertyNotWoven(
        string $class,
        string $propertyName,
        ?string $advisorIdentifier = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL
     *                                       if any.
     * @param null|int    $index             Index of advisor identifier, or null if order is not important.
     * @param string      $message           Assertion info message.
     */
    protected function assertClassInitializationWoven(
        string $class,
        ?string $advisorIdentifier = null,
        ?int $index = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or
     *                                       NULL if none should be registered.
     * @param string      $message           Assertion info message.
     */
    protected function assertClassInitializationNotWoven(
        string $class,
        ?string $advisorIdentifier = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier to be registered within proxy class, or NULL
     *                                       if any.
     * @param null|int    $index             Index of advisor identifier, or null if order is not important.
     * @param string      $message           Assertion info message.
     */
    protected function assertClassStaticInitializationWoven(
        string $class,
        ?string $advisorIdentifier = null,
        ?int $index = null,
        string $message = ''
    ): void {
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
     * @param string      $class             Full qualified class name which is subject of weaving.
     * @param null|string $advisorIdentifier Expected advisor identifier not to be registered within proxy class, or
     *                                       NULL if none should be registered.
     * @param string      $message           Assertion info message.
     */
    protected function assertClassStaticInitializationNotWoven(
        string $class,
        ?string $advisorIdentifier = null,
        string $message = ''
    ): void {
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
