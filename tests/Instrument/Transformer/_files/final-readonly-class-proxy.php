<?php
declare(strict_types=1);
namespace Test\ns1;
final readonly class TestReadonlyClass implements \Go\Aop\Proxy
{
    use \Test\ns1\TestReadonlyClass__AopProxied {
        \Test\ns1\TestReadonlyClass__AopProxied::publicMethod as private __aop__publicMethod;
        \Test\ns1\TestReadonlyClass__AopProxied::anotherMethod as private __aop__anotherMethod;
        \Test\ns1\TestReadonlyClass__AopProxied::staticMethod as private __aop__staticMethod;
    }
    public function publicMethod(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(self::class, 'publicMethod', ['advisor.Test\ns1\TestReadonlyClass->publicMethod']);
        }
        return $__joinPoint->__invoke($this);
    }
    public function anotherMethod(int $x): int
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(self::class, 'anotherMethod', ['advisor.Test\ns1\TestReadonlyClass->anotherMethod']);
        }
        return $__joinPoint->__invoke($this, [$x]);
    }
    public static function staticMethod(): string
    {
        /** @var \Go\Aop\Intercept\StaticMethodInvocation<self>|null $__joinPoint */
        static $__joinPoint;
        if ($__joinPoint === null) {
            $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forStaticMethod(self::class, 'staticMethod', ['advisor.Test\ns1\TestReadonlyClass->staticMethod']);
        }
        return $__joinPoint->__invoke(static::class);
    }
}
