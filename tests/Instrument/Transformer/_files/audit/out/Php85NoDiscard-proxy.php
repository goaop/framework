<?php
declare(strict_types=1);
namespace Repro;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
class Php85NoDiscard implements \Go\Aop\Proxy
{
    use Php85NoDiscard__AopProxied {
        Php85NoDiscard__AopProxied::computeTotal as private __aop__computeTotal;
        Php85NoDiscard__AopProxied::caller as private __aop__caller;
    }
    #[\NoDiscard('result must be used')]
    public function computeTotal(int $a, int $b): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'computeTotal', ['advisor.Repro\Php85NoDiscard->computeTotal'], $this->__aop__computeTotal(...));
        return $__joinPoint->__invoke($this, [$a, $b]);
    }
    public function caller(): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'caller', ['advisor.Repro\Php85NoDiscard->caller'], $this->__aop__caller(...));
        return $__joinPoint->__invoke($this);
    }
}