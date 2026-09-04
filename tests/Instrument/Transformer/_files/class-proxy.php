<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\StaticMethodInvocation;
class TestClass implements \Go\Aop\Proxy
{
    use TestClassOriginal {
        TestClassOriginal::publicMethod as private publicMethodOriginal;
        TestClassOriginal::protectedMethod as private protectedMethodOriginal;
        TestClassOriginal::publicStaticMethod as private publicStaticMethodOriginal;
        TestClassOriginal::protectedStaticMethod as private protectedStaticMethodOriginal;
        TestClassOriginal::publicMethodDynamicArguments as private publicMethodDynamicArgumentsOriginal;
        TestClassOriginal::publicMethodFixedArguments as private publicMethodFixedArgumentsOriginal;
        TestClassOriginal::methodWithSpecialTypeArguments as private methodWithSpecialTypeArgumentsOriginal;
    }
    public function publicMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'publicMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->publicMethod')),
            ],
            $this->publicMethodOriginal(...),
        );
        return $__joinPoint->__invoke($this);
    }
    protected function protectedMethod()
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'protectedMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->protectedMethod')),
            ],
            $this->protectedMethodOriginal(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public static function publicStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(
            self::class,
            'publicStaticMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->publicStaticMethod')),
            ],
            self::publicStaticMethodOriginal(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
    protected static function protectedStaticMethod()
    {
        /** @var StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forStaticMethod(
            self::class,
            'protectedStaticMethod',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->protectedStaticMethod')),
            ],
            self::protectedStaticMethodOriginal(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
    public function publicMethodDynamicArguments($a, &$b)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'publicMethodDynamicArguments',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->publicMethodDynamicArguments')),
            ],
            $this->publicMethodDynamicArgumentsOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$a, &$b]);
    }
    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'publicMethodFixedArguments',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->publicMethodFixedArguments')),
            ],
            $this->publicMethodFixedArgumentsOriginal(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }
    public function methodWithSpecialTypeArguments(self $instance)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'methodWithSpecialTypeArguments',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestClass->methodWithSpecialTypeArguments')),
            ],
            $this->methodWithSpecialTypeArgumentsOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$instance]);
    }
}
