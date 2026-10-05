<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\Interceptor as AopInterceptor;
use Go\Aop\Framework\The as AopThe;
use Go\Aop\Intercept\DynamicMethodInvocation;
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
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = Injector::forMethod(
            self::class,
            'log',
            [
                AopInterceptor::before(AopThe::advice('advisor.Test\ns1\ImportCollisionClass->log')),
            ],
            $this->logOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, match (\func_num_args()) {
            0 => [],
            1 => [$level],
            2 => [$level, $target],
            default => [$level, $target] + \func_get_args(),
        });
    }
    public function injector(): ?\Go\Aop\Framework\InterceptorInjector
    {
        /** @var DynamicMethodInvocation<self, ?\Go\Aop\Framework\InterceptorInjector> $__joinPoint */
        static $__joinPoint = Injector::forMethod(
            self::class,
            'injector',
            [
                AopInterceptor::before(AopThe::advice('advisor.Test\ns1\ImportCollisionClass->injector')),
            ],
            $this->injectorOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \func_get_args());
    }
}