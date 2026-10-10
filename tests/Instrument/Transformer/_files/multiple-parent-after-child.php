<?php
declare(strict_types=1);

namespace Test\sort;

class SortChild extends SortParent
{
    public function hello(): string
    {
        return 'child';
    }
}

class SortParent
{
    public function hello(): string
    {
        return 'parent';
    }
}
