<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
class GlobalParentCollection extends \ArrayObject implements \IteratorAggregate, \Traversable, \ArrayAccess, \Serializable, \Countable, \Go\Aop\Proxy
{
    use GlobalParentCollectionOriginalTrait {
        GlobalParentCollectionOriginalTrait::hello as private helloOriginalAlias;
    }
    public function hello(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'hello',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\GlobalParentCollection->hello')),
            ],
            $this->helloOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
