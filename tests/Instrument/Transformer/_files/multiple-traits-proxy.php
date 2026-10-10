<?php
declare(strict_types=1);
namespace Test\traits {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    trait FirstMultiTrait
    {
        use FirstMultiTraitOriginalTrait {
            FirstMultiTraitOriginalTrait::first as private firstOriginalAlias;
        }
        public function first(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'first',
                [
                    Interceptor::before(The::advice('advisor.Test\traits\FirstMultiTrait->first')),
                ],
                $this->firstOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}

namespace Test\traits {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    trait SecondMultiTrait
    {
        use SecondMultiTraitOriginalTrait {
            SecondMultiTraitOriginalTrait::second as private secondOriginalAlias;
        }
        public function second(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'second',
                [
                    Interceptor::before(The::advice('advisor.Test\traits\SecondMultiTrait->second')),
                ],
                $this->secondOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}