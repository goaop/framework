<?php
declare(strict_types=1);
namespace Test\ns1 {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\StaticMethodInvocation;
    class TestClass1 implements \Go\Aop\Proxy
    {
        use TestClass1OriginalTrait {
            TestClass1OriginalTrait::test as private testOriginalAlias;
        }
        public static function test()
        {
            /** @var StaticMethodInvocation<self> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forStaticMethod(
                self::class,
                'test',
                [
                    Interceptor::before(The::advice('advisor.Test\ns1\TestClass1->test')),
                ],
                self::testOriginalAlias(...),
            );
            return $__joinPoint->__invoke(static::class);
        }
    }
}

namespace Test\ns2 {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\StaticMethodInvocation;
    class TestClass2 implements \Go\Aop\Proxy
    {
        use TestClass2OriginalTrait {
            TestClass2OriginalTrait::test as private testOriginalAlias;
        }
        public static function test()
        {
            /** @var StaticMethodInvocation<self> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forStaticMethod(
                self::class,
                'test',
                [
                    Interceptor::before(The::advice('advisor.Test\ns2\TestClass2->test')),
                ],
                self::testOriginalAlias(...),
            );
            return $__joinPoint->__invoke(static::class);
        }
    }
}