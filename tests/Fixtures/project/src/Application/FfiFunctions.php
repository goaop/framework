<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Application;

/**
 * Calls internal functions the ffi driver proxies (function pointcuts): unqualified calls are
 * served by the namespaced proxy, a qualified call reaches the internal function directly
 */
final class FfiFunctions
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
