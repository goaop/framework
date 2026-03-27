<?php
declare(strict_types=1);
namespace Test\ns1;

trait TestReadonlyClass__AopProxied
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
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/final-readonly-class.php/Test/ns1/TestReadonlyClass.php';
