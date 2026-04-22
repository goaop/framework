<?php
declare(strict_types = 1);

namespace Go\Tests\TestProject\Application;

final class ArrayPropertyDemo
{
    protected array $indirectModificationCheck = [4, 5, 6];

    public function __construct()
    {
        array_push($this->indirectModificationCheck, 7, 8, 9);
    }

    public function countItems(): int
    {
        return count($this->indirectModificationCheck);
    }

    public function appendValue(int $value): void
    {
        array_push($this->indirectModificationCheck, $value);
    }
}
