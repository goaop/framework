<?php
declare(strict_types=1);
namespace Test\ns1;

class GlobalParentCollection extends \ArrayObject implements \Countable
{
    public function hello(): string
    {
        return 'hello';
    }
}
