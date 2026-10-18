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

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Features;

/**
 * The kernel options do not fit the stream wrapper driver
 */
final class StreamWrapperConfigurationException extends InvalidConfigurationException
{
    public static function includeInterceptionImplied(): self
    {
        return new self(sprintf(
            'The stream wrapper weaving driver intercepts every include of the application by itself: '
            . 'Features::INTERCEPT_INCLUDES (%d) would rewrite includes into php://filter URIs instead, '
            . 'which opcache never caches. Drop the flag from the "features" kernel option.',
            Features::INTERCEPT_INCLUDES,
        ));
    }
}
