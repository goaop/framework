<?php
declare(strict_types=1);
namespace Go\Tests\TestProject\Application;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Tests\TestProject\Application\PromotedPropertyClass;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
/**
 * Class with promoted constructor properties (multi-line constructor) used for
 * testing interception of promoted properties (issue #599).
 */
class PromotedPropertyClass implements \Go\Aop\Proxy
{
    use PromotedPropertyClass__AopProxied {
        PromotedPropertyClass__AopProxied::__construct as private __aop____construct;
        PromotedPropertyClass__AopProxied::getName as private __aop__getName;
    }
    private string $name = 'initial' {
        get {
            /** @var FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'name',
                [
                    Interceptor::before(The::aspect(PromotedPropertyClass::class)->name(...)),
                ],
            );
            return $__joinPoint->__invoke($this, FieldAccessType::READ, $this->name);
        }
        set {
            /** @var FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'name',
                [
                    Interceptor::before(The::aspect(PromotedPropertyClass::class)->name(...)),
                ],
            );
            $this->name = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this->name);
        }
    }
    final public private(set) int $counter = 1 {
        get {
            /** @var FieldAccess<self, int> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'counter',
                [
                    Interceptor::before(The::aspect(PromotedPropertyClass::class)->counter(...)),
                ],
            );
            return $__joinPoint->__invoke($this, FieldAccessType::READ, $this->counter);
        }
        set {
            /** @var FieldAccess<self, int> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'counter',
                [
                    Interceptor::before(The::aspect(PromotedPropertyClass::class)->counter(...)),
                ],
            );
            $this->counter = $__joinPoint->__invoke($this, FieldAccessType::WRITE, $value, $this->counter);
        }
    }
    public function __construct(string $name = 'initial', int $counter = 1, ?\ArrayObject $bag = null)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                Interceptor::before(The::aspect(PromotedPropertyClass::class)->__construct(...)),
            ],
            $this->__aop____construct(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$name, $counter, $bag], 0, \func_num_args()));
    }
    public function getName(): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'getName',
            [
                Interceptor::before(The::aspect(PromotedPropertyClass::class)->getName(...)),
            ],
            $this->__aop__getName(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
