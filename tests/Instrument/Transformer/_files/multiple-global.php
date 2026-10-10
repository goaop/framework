<?php
declare(strict_types=1);

class GlobalMultiFirst
{
    public function hello(): string
    {
        return 'first';
    }
}

$globalMultiName = GlobalMultiFirst::class;

class GlobalMultiSecond
{
    public function hello(): string
    {
        return 'second';
    }
}
