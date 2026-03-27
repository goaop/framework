<?php
declare(strict_types=1);
namespace Test\ns1;

final readonly class TestReadonlyClass
{
    public function publicMethod(): string
    {
        return 'hello';
    }

    public function anotherMethod(int $x): int
    {
        return $x * 2;
    }

    public static function staticMethod(): string
    {
        return static::class;
    }
}
