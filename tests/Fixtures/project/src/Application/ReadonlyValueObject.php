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

namespace Go\Tests\TestProject\Application;

/**
 * Readonly class with a declared and a promoted property, both implicitly readonly (#792)
 */
final readonly class ReadonlyValueObject
{
    public int $scale;

    public function __construct(public int $value, int $scale = 2)
    {
        $this->scale = $scale;
    }

    public function scaled(): int
    {
        return $this->value * $this->scale;
    }
}
