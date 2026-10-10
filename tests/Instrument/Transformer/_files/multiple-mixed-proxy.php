<?php
declare(strict_types=1);
namespace Test\mixed {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    class MixedFirst implements \Test\mixed\MixedContract, \Go\Aop\Proxy
    {
        use MixedFirstOriginalTrait {
            MixedFirstOriginalTrait::run as private runOriginalAlias;
        }
        public function run(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'run',
                [
                    Interceptor::before(The::advice('advisor.Test\mixed\MixedFirst->run')),
                ],
                $this->runOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}

namespace Test\mixed {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    enum MixedSuit : string implements \Go\Aop\Proxy
    {
        use MixedSuitOriginalTrait {
            MixedSuitOriginalTrait::label as private labelOriginalAlias;
        }
        case Hearts = 'H';
        public function label(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'label',
                [
                    Interceptor::before(The::advice('advisor.Test\mixed\MixedSuit->label')),
                ],
                $this->labelOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}

namespace Test\mixed {
    use Go\Aop\Framework\InterceptorInjector;
    use Go\Aop\Framework\Interceptor;
    use Go\Aop\Framework\The;
    use Go\Aop\Intercept\DynamicMethodInvocation;
    class MixedChild extends \Test\mixed\MixedPlain implements \Go\Aop\Proxy
    {
        use MixedChildOriginalTrait {
            MixedChildOriginalTrait::hello as private helloOriginalAlias;
        }
        public function hello(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'hello',
                [
                    Interceptor::before(The::advice('advisor.Test\mixed\MixedChild->hello')),
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
    class MixedGlobal implements \Go\Aop\Proxy
    {
        use MixedGlobalOriginalTrait {
            MixedGlobalOriginalTrait::hello as private helloOriginalAlias;
        }
        public function hello(): string
        {
            /** @var DynamicMethodInvocation<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forMethod(
                self::class,
                'hello',
                [
                    Interceptor::before(The::advice('advisor.MixedGlobal->hello')),
                ],
                $this->helloOriginalAlias(...),
            );
            return $__joinPoint->__invoke($this);
        }
    }
}