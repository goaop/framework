<?php
declare(strict_types=1);
namespace Go\Tests\TestProject\Application;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
/**
 * Class with promoted constructor properties (multi-line constructor) used for
 * testing interception of promoted properties (issue #599).
 */
class PromotedPropertyClass implements \Go\Aop\Proxy
{
    use PromotedPropertyClassOriginal {
        PromotedPropertyClassOriginal::__construct as private __constructOriginal;
        PromotedPropertyClassOriginal::getName as private getNameOriginal;
    }
    private string $name = 'initial' {
        get {
            /** @var FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = InterceptorInjector::forProperty(
                self::class,
                'name',
                [
                    Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name')),
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
                    Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name')),
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
                    Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter')),
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
                    Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter')),
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
                Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->__construct')),
            ],
            $this->__constructOriginal(...),
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
                Interceptor::before(The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->getName')),
            ],
            $this->getNameOriginal(...),
        );
        return $__joinPoint->__invoke($this);
    }
}