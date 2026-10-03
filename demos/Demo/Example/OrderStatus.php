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
 * Backed enum whose methods are intercepted like the methods of a class
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    /**
     * Human-readable label of the status
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Status that follows the given one
     */
    public static function next(self $status): self
    {
        return match ($status) {
            self::Pending => self::Shipped,
            self::Shipped, self::Delivered => self::Delivered,
        };
    }
}
