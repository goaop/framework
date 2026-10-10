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

namespace Go\Instrument\ZEngine;

use Closure;
use Go\Aop\Exception\InvalidConfigurationException;

/**
 * Makes sure the opcache JIT is off before the engine driver touches the executor
 *
 * z-engine hooks the executor internals the JIT bypasses, so the JIT has to be off for the
 * whole process (`opcache.jit=off` with `opcache.jit_buffer_size=0` in php.ini, the FPM pool
 * or `php -d`). On PHP 8.5 the tracing JIT additionally miscompiles code the framework runs
 * (a known PHP bug), so there the guard switches the JIT off at runtime as a safety net.
 * That only covers code compiled AFTER the switch: Composer, z-engine's own classes and the
 * application bootstrap were compiled before the kernel ran and keep their JIT hot counters,
 * which is why the process-level setting stays the documented requirement and the report
 * records what happened.
 *
 * Detection goes through `opcache_get_status()['jit']['on']`: the ini value is not usable
 * (`-d opcache.jit=off` reads back as an empty string, `opcache.jit=tracing` with
 * `opcache.jit_buffer_size=0` still reads "tracing" while the JIT is off).
 *
 * @phpstan-type JitReport array{jitWasActive: bool, previousMode: string|null, disabledAtRuntime: bool}
 *
 * @internal Framework service, not a public extension point
 */
final class JitGuard
{
    /**
     * Static facade, never instantiated
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    /**
     * Enforces the JIT policy for the running process
     *
     * @param int|null                                  $phpVersionId PHP_VERSION_ID override (tests)
     * @param (Closure(): (array<array-key, mixed>|false))|null $statusReader opcache_get_status(false) replacement (tests)
     * @param (Closure(string, string): (string|false))|null $iniWriter    ini_set() replacement (tests)
     *
     * @return JitReport
     *
     * @throws InvalidConfigurationException When the JIT is on and may not be disabled here
     */
    public static function enforce(?int $phpVersionId = null, ?Closure $statusReader = null, ?Closure $iniWriter = null): array
    {
        $phpVersionId ??= PHP_VERSION_ID;
        $statusReader ??= static fn(): array|false => function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $iniWriter    ??= static fn(string $option, string $value): string|false => ini_set($option, $value);

        $report = ['jitWasActive' => false, 'previousMode' => null, 'disabledAtRuntime' => false];
        if (!self::isJitOn($statusReader())) {
            return $report;
        }
        $report['jitWasActive'] = true;

        $isPhp85 = $phpVersionId >= 80500 && $phpVersionId < 80600;
        if (!$isPhp85) {
            throw new InvalidConfigurationException(
                'The zengine weaving driver requires the opcache JIT to be off: z-engine hooks the executor '
                . 'internals the JIT bypasses. Start PHP with opcache.jit=off and opcache.jit_buffer_size=0 '
                . '(php.ini, the FPM pool or `php -d`).',
            );
        }
        // PHP 8.5: the tracing JIT miscompiles code the framework runs (known PHP bug), so the
        // driver switches it off for the rest of the request rather than refusing to run.
        // "off" (never "disable", which locks the directive for the rest of the process)
        $previousMode            = $iniWriter('opcache.jit', 'off');
        $report['previousMode']  = is_string($previousMode) ? $previousMode : null;
        if (self::isJitOn($statusReader())) {
            throw new InvalidConfigurationException(
                'The zengine weaving driver could not switch the opcache JIT off at runtime (PHP 8.5 tracing '
                . 'JIT bug): start PHP with opcache.jit=off and opcache.jit_buffer_size=0 (php.ini, the FPM '
                . 'pool or `php -d`).',
            );
        }
        $report['disabledAtRuntime'] = true;

        return $report;
    }

    /**
     * @param array<array-key, mixed>|false $status Result of opcache_get_status(false)
     */
    private static function isJitOn(array|false $status): bool
    {
        if ($status === false || !isset($status['jit']) || !is_array($status['jit'])) {
            return false;
        }

        return ($status['jit']['on'] ?? false) === true;
    }
}
