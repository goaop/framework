<?php
declare(strict_types=1);
namespace Repro;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
class Php85PipeOperator implements \Go\Aop\Proxy
{
    use Php85PipeOperator__AopProxied {
        Php85PipeOperator__AopProxied::transform as private __aop__transform;
        Php85PipeOperator__AopProxied::withNewOnRhs as private __aop__withNewOnRhs;
    }
    public function transform(string $input): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'transform', ['advisor.Repro\Php85PipeOperator->transform'], $this->__aop__transform(...));
        return $__joinPoint->__invoke($this, [$input]);
    }
    public function withNewOnRhs(string $value): \ArrayObject
    {
        /** @var DynamicMethodInvocation<self, \ArrayObject> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'withNewOnRhs', ['advisor.Repro\Php85PipeOperator->withNewOnRhs'], $this->__aop__withNewOnRhs(...));
        return $__joinPoint->__invoke($this, [$value]);
    }
}