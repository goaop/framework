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

/**
 * Product with PHP 8.4 property features: hooks and asymmetric visibility
 */
class ProductDemo
{
    /**
     * Plain property: the proxy re-declares it with generated hooks, so "access" advices see it
     */
    public float $price = 0.0;

    /**
     * Asymmetric visibility: readable everywhere, writable only inside the class, intercepted as well
     */
    public private(set) int $stock = 0;

    /**
     * Property with its own hook: never intercepted, the hook keeps working as written
     */
    public string $sku {
        set => strtoupper($value);
    }

    public function __construct(string $sku)
    {
        $this->sku = $sku;
    }

    public function restock(int $amount): void
    {
        $this->stock += $amount;
    }
}
