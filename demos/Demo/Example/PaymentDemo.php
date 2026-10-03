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
 * Payment gateway whose failures are reported by an "AfterThrowing" advice
 */
class PaymentDemo
{
    /**
     * Charges the card, throws for a non-positive amount
     */
    public function charge(string $card, int $amount): string
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Amount must be positive, {$amount} given");
        }

        return "Charged {$amount} to the card {$card}";
    }
}
