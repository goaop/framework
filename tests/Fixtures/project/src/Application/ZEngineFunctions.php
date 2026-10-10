<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Calls internal functions the z-engine driver proxies (Features::INTERCEPT_FUNCTIONS): unqualified calls are
 * served by the namespaced proxy, a qualified call reaches the internal function directly
 */
final class ZEngineFunctions
{
    /**
     * @param list<int> $numbers
     */
    public function total(array $numbers): int
    {
        return (int) array_sum($numbers);
    }

    /**
     * @param list<int> $numbers
     */
    public function totalQualified(array $numbers): int
    {
        return (int) \array_sum($numbers);
    }
}
