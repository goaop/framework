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

namespace Go\Aop;

use Go\Aop\Exception\InvalidConfigurationException;

/**
 * Weaving drivers selectable through the `driver` kernel option
 *
 * The driver decides HOW advised classes are rewired to the join-point runtime; everything
 * else (aspects, pointcuts, advisor cache, the interceptor chain) is shared.
 */
enum WeavingDriver: string
{
    /**
     * Source transformation at load time: a composer autoloader wrapper redirects advised
     * files through the `php://filter` stream filter, which converts the class to a trait
     * and generates a proxy class into the cache directory (the default, pure PHP)
     */
    case Stream = 'stream';

    /**
     * Runtime method-table weaving through lisachenko/z-engine (PHP FFI): classes load
     * natively, and right after a class is loaded the body of every advised method is
     * swapped in place with a generated dispatcher while the original body stays callable
     * under a private alias. No source rewriting, no stream filter, no include interception.
     * Requires ext-ffi, the z-engine branch matching the PHP minor, and the opcache JIT off.
     */
    case ZEngine = 'zengine';

    /**
     * Resolves the `driver` kernel option: the enum itself or its string value
     *
     * @throws InvalidConfigurationException For anything else
     */
    public static function fromOption(mixed $option): self
    {
        if ($option instanceof self) {
            return $option;
        }
        $driver = is_string($option) ? self::tryFrom($option) : null;
        if ($driver === null) {
            throw new InvalidConfigurationException(sprintf(
                'Option "driver" must be one of %s (or a Go\\Aop\\WeavingDriver case), got %s.',
                implode(', ', array_map(static fn(self $case): string => '"' . $case->value . '"', self::cases())),
                is_string($option) ? '"' . $option . '"' : get_debug_type($option),
            ));
        }

        return $driver;
    }
}
