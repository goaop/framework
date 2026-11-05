<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\StaticMethodInvocation;
use Go\Aop\Proxy;
final readonly class TestReadonlyClass implements Proxy
{
    use TestReadonlyClassOriginalTrait {
        TestReadonlyClassOriginalTrait::__construct as private __constructOriginalAlias;
        TestReadonlyClassOriginalTrait::publicMethod as private publicMethodOriginalAlias;
        TestReadonlyClassOriginalTrait::anotherMethod as private anotherMethodOriginalAlias;
        TestReadonlyClassOriginalTrait::staticMethod as private staticMethodOriginalAlias;
    }
    public function __construct(string $name = 'point', int $limit = 10)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestReadonlyClass->__construct')),
            ],
            $this->__constructOriginalAlias(...),
        );
        $__joinPoint->__invoke($this, \array_slice([$name, $limit], 0, \func_num_args()));
    }
    public function publicMethod(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'publicMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestReadonlyClass->publicMethod')),
            ],
            $this->publicMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public function anotherMethod(int $x): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'anotherMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestReadonlyClass->anotherMethod')),
            ],
            $this->anotherMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$x]);
    }
    public static function staticMethod(): string
    {
        /** @var StaticMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(
            self::class,
            'staticMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestReadonlyClass->staticMethod')),
            ],
            self::staticMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
}
