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

class ByReferenceStub
{
    /**
     * @var list<int>
     */
    private array $items = [];

    /**
     * @return list<int>
     */
    public function &items(): array
    {
        return $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
