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

namespace Demo\Example;

use InvalidArgumentException;

/**
 * Order processor that keeps its steps in private and protected methods
 */
class OrderProcessorDemo
{
    /**
     * Processes an order: only this method is public, every step is intercepted anyway
     *
     * @param array<string, float> $items Prices indexed by item name
     */
    public function process(array $items): float
    {
        $this->validate($items);
        $total = $this->calculateTotal($items);

        return self::applyDiscount($total);
    }

    /**
     * @param array<string, float> $items
     */
    private function validate(array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('Order is empty');
        }
    }

    /**
     * @param array<string, float> $items
     */
    protected function calculateTotal(array $items): float
    {
        return array_sum($items);
    }

    private static function applyDiscount(float $total): float
    {
        return $total > 100 ? round($total * 0.9, 2) : $total;
    }
}
