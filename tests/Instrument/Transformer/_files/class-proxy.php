<?php
declare(strict_types=1);
namespace Test\ns1;
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
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'publicMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->publicMethod')),
            ],
            $this->publicMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    protected function protectedMethod()
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'protectedMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->protectedMethod')),
            ],
            $this->protectedMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
    public static function publicStaticMethod()
    {
        /** @var \Go\Aop\Intercept\StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forStaticMethod(
            self::class,
            'publicStaticMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->publicStaticMethod')),
            ],
            self::publicStaticMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
    protected static function protectedStaticMethod()
    {
        /** @var \Go\Aop\Intercept\StaticMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forStaticMethod(
            self::class,
            'protectedStaticMethod',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->protectedStaticMethod')),
            ],
            self::protectedStaticMethodOriginalAlias(...),
        );
        return $__joinPoint->__invoke(static::class);
    }
    public function publicMethodDynamicArguments($a, &$b)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'publicMethodDynamicArguments',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->publicMethodDynamicArguments')),
            ],
            $this->publicMethodDynamicArgumentsOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$a, &$b]);
    }
    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'publicMethodFixedArguments',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->publicMethodFixedArguments')),
            ],
            $this->publicMethodFixedArgumentsOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }
    public function methodWithSpecialTypeArguments(self $instance)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'methodWithSpecialTypeArguments',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestClass->methodWithSpecialTypeArguments')),
            ],
            $this->methodWithSpecialTypeArgumentsOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$instance]);
    }
}
