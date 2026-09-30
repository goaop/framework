<?php
declare(strict_types=1);
namespace Test\ns1;
final readonly class TestReadonlyClass implements \Go\Aop\Proxy
{
    use TestReadonlyClassOriginalTrait {
        TestReadonlyClassOriginalTrait::publicMethod as private publicMethodOriginalAlias;
        TestReadonlyClassOriginalTrait::anotherMethod as private anotherMethodOriginalAlias;
        TestReadonlyClassOriginalTrait::staticMethod as private staticMethodOriginalAlias;
    }
    public function publicMethod(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'publicMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestReadonlyClass->publicMethod')),
            ],
            $this->publicMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public function anotherMethod(int $x): int
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'anotherMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestReadonlyClass->anotherMethod')),
            ],
            $this->anotherMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$x]);
    }
    public static function staticMethod(): string
    {
        /** @var \Go\Aop\Intercept\StaticMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forStaticMethod(
            self::class,
            'staticMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestReadonlyClass->staticMethod')),
            ],
            self::staticMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
}
