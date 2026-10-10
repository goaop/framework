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

/**
 * Result of weaving one class through the z-engine driver
 */
final readonly class WeaveResult
{
    /**
     * @param WeaveOutcome $outcome Outcome
     * @param list<string> $methods Names of the methods rewired through the engine (own methods
     *                              keep their body under `<method>OriginalAlias`, inherited ones
     *                              got an override calling `parent::<method>()`)
     * @param string|null  $reason  Why the class was skipped, for WeaveOutcome::Skipped
     */
    public function __construct(
        public WeaveOutcome $outcome,
        public array $methods = [],
        public ?string $reason = null,
    ) {}

    /**
     * @param list<string> $methods
     */
    public static function woven(array $methods): self
    {
        return new self(WeaveOutcome::Woven, $methods);
    }

    public static function noAdvices(): self
    {
        return new self(WeaveOutcome::NoAdvices);
    }

    /**
     * @param list<string> $methods
     */
    public static function alreadyWoven(array $methods): self
    {
        return new self(WeaveOutcome::AlreadyWoven, $methods);
    }

    public static function skipped(string $reason): self
    {
        return new self(WeaveOutcome::Skipped, [], $reason);
    }
}
