<?php
declare(strict_types=1);
namespace Go\Tests\TestProject\Application;

/**
 * Class with promoted constructor properties (multi-line constructor) used for
 * testing interception of promoted properties (issue #599).
 */
class PromotedPropertyClass implements \Go\Aop\Proxy
{
    use PromotedPropertyClassOriginalTrait {
        PromotedPropertyClassOriginalTrait::__construct as private __constructOriginalAlias;
        PromotedPropertyClassOriginalTrait::getName as private getNameOriginalAlias;
    }
    private string $name = 'initial' {
        get {
            /** @var \Go\Aop\Intercept\FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'name',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name')),
                ],
            );
            return $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::READ, $this->name);
        }
        set {
            /** @var \Go\Aop\Intercept\FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'name',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->name')),
                ],
            );
            $this->name = $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::WRITE, $value, $this->name);
        }
    }
    final public private(set) int $counter = 1 {
        get {
            /** @var \Go\Aop\Intercept\FieldAccess<self, int> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'counter',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter')),
                ],
            );
            return $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::READ, $this->counter);
        }
        set {
            /** @var \Go\Aop\Intercept\FieldAccess<self, int> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'counter',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->counter')),
                ],
            );
            $this->counter = $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::WRITE, $value, $this->counter);
        }
    }
    public function __construct(string $name = 'initial', int $counter = 1, ?\ArrayObject $bag = null)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->__construct')),
            ],
            $this->__constructOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$name, $counter, $bag], 0, \func_num_args()));
    }
    public function getName(): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'getName',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\PromotedPropertyClass->getName')),
            ],
            $this->getNameOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}