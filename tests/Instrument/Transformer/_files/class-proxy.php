<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\StaticMethodInvocation;
class TestClass implements \Go\Aop\Proxy
{
    use \Test\ns1\TestClass__AopProxied {
        \Test\ns1\TestClass__AopProxied::publicMethod as private __aop__publicMethod;
        \Test\ns1\TestClass__AopProxied::protectedMethod as private __aop__protectedMethod;
        \Test\ns1\TestClass__AopProxied::publicStaticMethod as private __aop__publicStaticMethod;
        \Test\ns1\TestClass__AopProxied::protectedStaticMethod as private __aop__protectedStaticMethod;
        \Test\ns1\TestClass__AopProxied::publicMethodDynamicArguments as private __aop__publicMethodDynamicArguments;
        \Test\ns1\TestClass__AopProxied::publicMethodFixedArguments as private __aop__publicMethodFixedArguments;
        \Test\ns1\TestClass__AopProxied::methodWithSpecialTypeArguments as private __aop__methodWithSpecialTypeArguments;
    }
    public function publicMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethod', ['advisor.Test\ns1\TestClass->publicMethod']);
        return $__joinPoint->__invoke($this);
    }
    protected function protectedMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'protectedMethod', ['advisor.Test\ns1\TestClass->protectedMethod']);
        return $__joinPoint->__invoke($this);
    }
    public static function publicStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(self::class, 'publicStaticMethod', ['advisor.Test\ns1\TestClass->publicStaticMethod']);
        return $__joinPoint->__invoke(static::class);
    }
    protected static function protectedStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(self::class, 'protectedStaticMethod', ['advisor.Test\ns1\TestClass->protectedStaticMethod']);
        return $__joinPoint->__invoke(static::class);
    }
    public function publicMethodDynamicArguments($a, &$b)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethodDynamicArguments', ['advisor.Test\ns1\TestClass->publicMethodDynamicArguments']);
        return $__joinPoint->__invoke($this, [$a, &$b]);
    }
    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethodFixedArguments', ['advisor.Test\ns1\TestClass->publicMethodFixedArguments']);
        return $__joinPoint->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }
    public function methodWithSpecialTypeArguments(self $instance)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'methodWithSpecialTypeArguments', ['advisor.Test\ns1\TestClass->methodWithSpecialTypeArguments']);
        return $__joinPoint->__invoke($this, [$instance]);
    }
}
