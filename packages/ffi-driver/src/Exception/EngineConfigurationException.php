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

namespace Go\Driver\Ffi\Exception;

use Go\Aop\Exception\InvalidConfigurationException;
use Go\Aop\Features;
use Throwable;

/**
 * The host or the kernel options do not fit the ffi driver: z-engine missing or refusing to boot, the opcache
 * JIT on, a feature flag the driver cannot honour
 */
final class EngineConfigurationException extends InvalidConfigurationException
{
    public static function engineNotInstalled(): self
    {
        return new self(
            'The ffi weaving driver requires lisachenko/z-engine with ext-ffi enabled: '
            . 'composer require lisachenko/z-engine (composer picks the release of the running PHP minor).',
        );
    }

    public static function engineBootFailed(Throwable $previous): self
    {
        return new self('The ffi weaving driver cannot boot z-engine: ' . $previous->getMessage(), 0, $previous);
    }

    public static function jitStillOn(): self
    {
        return new self(
            'The ffi weaving driver requires the opcache JIT to be off (z-engine hooks the executor internals the '
            . 'JIT bypasses) and could not switch it off at runtime: start PHP with opcache.jit=off and '
            . 'opcache.jit_buffer_size=0 (php.ini, the FPM pool or `php -d`).',
        );
    }

    public static function includeInterceptionNotSupported(): self
    {
        return new self(sprintf(
            'The ffi weaving driver rewires loaded classes in the engine and never rewrites sources: '
            . 'Features::INTERCEPT_INCLUDES (%d) is only available with a source-transforming driver.',
            Features::INTERCEPT_INCLUDES,
        ));
    }
}
