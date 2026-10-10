<?php

declare(strict_types=1);

namespace Go\Stubs\ZEngine;

/**
 * An ordinary class the MatcherErrorAdvisor pointcut fails on: its load fails
 */
final class MatcherErrorTarget
{
    public function run(): string
    {
        return 'ran';
    }
}
