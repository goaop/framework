<?php
declare(strict_types=1);

namespace Test\nogap;

trait NoGapHelper
{
}

class NoGapFirst
{
    public function hello(): string
    {
        return 'first';
    }
}

$isFirst = isset($object) && $object instanceof NoGapFirst;
$name    = NoGapFirst::class;
$factory = static fn(): NoGapFirst => new NoGapFirst();

function createFirst(): NoGapFirst
{
    return new NoGapFirst();
}

class NoGapSecond
{
    use NoGapHelper;

    public function hello(): string
    {
        return 'second';
    }
}

$first  = new NoGapFirst();
$second = new NoGapSecond();
