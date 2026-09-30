<?php
declare(strict_types=1);
namespace Go\Tests\TestProject\Application;

/**
 * Class with a promoted constructor property in a single-line constructor used for
 * testing interception of promoted properties (issue #599).
 */
class SingleLinePromotedClass implements \Go\Aop\Proxy
{
    use SingleLinePromotedClassOriginalTrait {
        SingleLinePromotedClassOriginalTrait::__construct as private __constructOriginalAlias;
    }
    public string $tag = 'default' {
        get {
            /** @var \Go\Aop\Intercept\FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'tag',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag')),
                ],
            );
            return $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::READ, $this->tag);
        }
        set {
            /** @var \Go\Aop\Intercept\FieldAccess<self, string> $__joinPoint */
            static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forProperty(
                self::class,
                'tag',
                [
                    \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->tag')),
                ],
            );
            $this->tag = $__joinPoint->__invoke($this, \Go\Aop\Intercept\FieldAccessType::WRITE, $value, $this->tag);
        }
    }
    public function __construct(string $tag = 'default')
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Go\Tests\TestProject\Application\SingleLinePromotedClass->__construct')),
            ],
            $this->__constructOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$tag], 0, \func_num_args()));
    }
}