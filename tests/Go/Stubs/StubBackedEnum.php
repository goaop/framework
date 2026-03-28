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

namespace Go\Stubs;

/**
 * Stub backed enum used by EnumProxyGeneratorTest.
 */
enum StubBackedEnum: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match($this) {
            self::Active   => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match($label) {
            'Active'   => self::Active,
            'Inactive' => self::Inactive,
        };
    }
}
