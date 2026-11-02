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
 * Test fixture (issue #781): woven parent declaring the methods its woven child only inherits
 */
abstract class Issue781AbstractGateway
{
    public function __construct(private readonly string $name) {}

    public static function create(string $name): static
    {
        return new static($name);
    }

    /**
     * @return list<string>
     */
    public function references(): array
    {
        return [$this->name];
    }

    public function count(): int
    {
        return 1;
    }
}
