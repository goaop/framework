<?php
declare(strict_types=1);
namespace Test\ns1;

trait TestReadonlyClassOriginalTrait
{
    public readonly int $limit;

    public function __construct(public readonly string $name = 'point', int $limit = 10)
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
include_once AOP_CACHE_DIR . '/Transformer/_files/final-readonly-class.php';
