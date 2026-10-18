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

namespace Go\Driver\StreamWrapper\Exception;

use Go\Aop\Exception\ExceptionInterface;
use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Exception\WeavingException;
use PHPUnit\Framework\TestCase;

/**
 * The exceptions of the package: grouped below the framework exceptions, messages naming condition and remedy
 */
final class StreamWrapperExceptionsTest extends TestCase
{
    public function testTheRegistrationFailureNamesTheWrapperAndTheRestoredState(): void
    {
        $exception = StreamWrapperRegistrationException::fileWrapperNotReplaced('App\Wrapper');

        $this->assertInstanceOf(WeavingException::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertSame(
            'The stream wrapper weaving driver could not register App\Wrapper as the "file://" stream wrapper '
            . '(stream_wrapper_register() failed); the native wrapper was restored.',
            $exception->getMessage(),
        );
    }

    public function testTheImpliedIncludeInterceptionIsAConfigurationError(): void
    {
        $exception = StreamWrapperConfigurationException::includeInterceptionImplied();

        $this->assertInstanceOf(InvalidConfigurationException::class, $exception);
        $this->assertStringContainsString('INTERCEPT_INCLUDES', $exception->getMessage());
    }
}
