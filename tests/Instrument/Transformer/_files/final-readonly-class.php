<?php
declare(strict_types=1);
namespace Test\ns1;

final readonly class TestReadonlyClass
{
    public int $limit;

    public function __construct(public string $name = 'point', int $limit = 10)
    {
        $this->limit = $limit;
    }

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
