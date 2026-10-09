<?php
declare(strict_types=1);
namespace {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    class GlobalMultiFirst implements \Go\Aop\Proxy
    {
        use GlobalMultiFirstOriginalTrait {
            GlobalMultiFirstOriginalTrait::hello as private helloOriginalAlias;
        }
        public function hello(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'hello',
                [
                    Interceptor::before(The::advice('advisor.GlobalMultiFirst->hello')),
                ],
                $this->helloOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}

namespace {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    class GlobalMultiSecond implements \Go\Aop\Proxy
    {
        use GlobalMultiSecondOriginalTrait {
            GlobalMultiSecondOriginalTrait::hello as private helloOriginalAlias;
        }
        public function hello(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'hello',
                [
                    Interceptor::before(The::advice('advisor.GlobalMultiSecond->hello')),
                ],
                $this->helloOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}