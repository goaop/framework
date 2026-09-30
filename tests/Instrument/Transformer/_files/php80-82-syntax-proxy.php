<?php
declare(strict_types=1);
namespace Test\ns1;

/**
 * Compact class covering general PHP 8.0-8.3 syntax through the weaver:
 * constructor promotion (non-intercepted property), new-in-initializer parameter
 * default, named arguments, match expression, nullsafe operator, enum usage,
 * readonly property, first-class callable and a typed class constant.
 */
class TestPhp80To82SyntaxClass implements \Go\Aop\Proxy
{
    use TestPhp80To82SyntaxClassOriginalTrait {
        TestPhp80To82SyntaxClassOriginalTrait::__construct as private __constructOriginalAlias;
        TestPhp80To82SyntaxClassOriginalTrait::describe as private describeOriginalAlias;
    }
    public function __construct(string $label = 'default', \ArrayObject $items = new \ArrayObject([1, 2, 3]))
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp80To82SyntaxClass->__construct')),
            ],
            $this->__constructOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$label, $items], 0, \func_num_args()));
    }
    public function describe(?\ArrayObject $extra = null): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'describe',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp80To82SyntaxClass->describe')),
            ],
            $this->describeOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$extra], 0, \func_num_args()));
    }
}