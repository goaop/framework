<?php
declare(strict_types=1);

namespace Test\traits;

trait FirstMultiTrait
{
    public function first(): string
    {
        return 'first';
    }
}

trait SecondMultiTrait
{
    public function second(): string
    {
        return 'second';
    }
}
