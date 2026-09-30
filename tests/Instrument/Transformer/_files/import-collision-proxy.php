<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Stubs\Collision\Interceptor as Interceptor;
use Go\Stubs\Collision\The as The;
use Go\Aop\Framework\InterceptorInjector as Injector;
/**
 * Imports bind the short names used by generated proxies (Interceptor, The) to unrelated
 * classes, and alias a framework class: the proxy must neither redeclare nor rely on them
 */
class ImportCollisionClass implements \Go\Aop\Proxy
{
    use ImportCollisionClassOriginalTrait {
        ImportCollisionClassOriginalTrait::log as private logOriginalAlias;
        ImportCollisionClassOriginalTrait::injector as private injectorOriginalAlias;
    }
    public function log(string $level = Interceptor::LEVEL, ?\Go\Stubs\Collision\The $target = null): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'log',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\ImportCollisionClass->log')),
            ],
            $this->logOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$level, $target], 0, \func_num_args()));
    }
    public function injector(): ?\Go\Aop\Framework\InterceptorInjector
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, ?\Go\Aop\Framework\InterceptorInjector> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'injector',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\ImportCollisionClass->injector')),
            ],
            $this->injectorOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}