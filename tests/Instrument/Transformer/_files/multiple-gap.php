<?php
declare(strict_types=1);

namespace Test\gap;

trait GapHelper
{
}

class GapFirst
{
    public function hello(): string
    {
        return 'first';
    }
}

$first = new GapFirst();

class GapChild extends GapFirst
{
}

trait GapTrait
{
    public function traitHello(): string
    {
        return 'trait';
    }
}

class GapSecond
{
    use GapHelper;
    use GapTrait;

    public function hello(): string
    {
        return 'second';
    }
}
