<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\StaticMethodInvocation;
class TestClass implements \Go\Aop\Proxy
{
    use TestClass__AopProxied {
        TestClass__AopProxied::publicMethod as private __aop__publicMethod;
        TestClass__AopProxied::protectedMethod as private __aop__protectedMethod;
        TestClass__AopProxied::publicStaticMethod as private __aop__publicStaticMethod;
        TestClass__AopProxied::protectedStaticMethod as private __aop__protectedStaticMethod;
        TestClass__AopProxied::publicMethodDynamicArguments as private __aop__publicMethodDynamicArguments;
        TestClass__AopProxied::publicMethodFixedArguments as private __aop__publicMethodFixedArguments;
        TestClass__AopProxied::methodWithSpecialTypeArguments as private __aop__methodWithSpecialTypeArguments;
    }
    public function publicMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethod', ['advisor.Test\ns1\TestClass->publicMethod'], $this->__aop__publicMethod(...));
        return $__joinPoint->__invoke($this);
    }
    protected function protectedMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'protectedMethod', ['advisor.Test\ns1\TestClass->protectedMethod'], $this->__aop__protectedMethod(...));
        return $__joinPoint->__invoke($this);
    }
    public static function publicStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(self::class, 'publicStaticMethod', ['advisor.Test\ns1\TestClass->publicStaticMethod'], self::__aop__publicStaticMethod(...));
        return $__joinPoint->__invoke(static::class);
    }
    protected static function protectedStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(self::class, 'protectedStaticMethod', ['advisor.Test\ns1\TestClass->protectedStaticMethod'], self::__aop__protectedStaticMethod(...));
        return $__joinPoint->__invoke(static::class);
    }
    public function publicMethodDynamicArguments($a, &$b)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethodDynamicArguments', ['advisor.Test\ns1\TestClass->publicMethodDynamicArguments'], $this->__aop__publicMethodDynamicArguments(...));
        return $__joinPoint->__invoke($this, [$a, &$b]);
    }
    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'publicMethodFixedArguments', ['advisor.Test\ns1\TestClass->publicMethodFixedArguments'], $this->__aop__publicMethodFixedArguments(...));
        return $__joinPoint->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }
    public function methodWithSpecialTypeArguments(self $instance)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'methodWithSpecialTypeArguments', ['advisor.Test\ns1\TestClass->methodWithSpecialTypeArguments'], $this->__aop__methodWithSpecialTypeArguments(...));
        return $__joinPoint->__invoke($this, [$instance]);
    }
}
