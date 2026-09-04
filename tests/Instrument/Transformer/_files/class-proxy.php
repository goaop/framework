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
    use TestClassOriginalTrait {
        TestClassOriginalTrait::publicMethod as private publicMethodOriginalAlias;
        TestClassOriginalTrait::protectedMethod as private protectedMethodOriginalAlias;
        TestClassOriginalTrait::publicStaticMethod as private publicStaticMethodOriginalAlias;
        TestClassOriginalTrait::protectedStaticMethod as private protectedStaticMethodOriginalAlias;
        TestClassOriginalTrait::publicMethodDynamicArguments as private publicMethodDynamicArgumentsOriginalAlias;
        TestClassOriginalTrait::publicMethodFixedArguments as private publicMethodFixedArgumentsOriginalAlias;
        TestClassOriginalTrait::methodWithSpecialTypeArguments as private methodWithSpecialTypeArgumentsOriginalAlias;
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
            $this->publicMethodOriginalAlias(...),
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
            $this->protectedMethodOriginalAlias(...),
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
            self::publicStaticMethodOriginalAlias(...),
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
            self::protectedStaticMethodOriginalAlias(...),
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
            $this->publicMethodDynamicArgumentsOriginalAlias(...),
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
            $this->publicMethodFixedArgumentsOriginalAlias(...),
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
            $this->methodWithSpecialTypeArgumentsOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$instance]);
    }
}
