<?php
declare(strict_types=1);
namespace Test\ns1;

use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
/**
 * Compact class covering general PHP 8.0-8.3 syntax through the weaver:
 * constructor promotion (non-intercepted property), new-in-initializer parameter
 * default, named arguments, match expression, nullsafe operator, enum usage,
 * readonly property, first-class callable and a typed class constant.
 */
class TestPhp80To82SyntaxClass implements \Go\Aop\Proxy
{
    use TestPhp80To82SyntaxClassOriginal {
        TestPhp80To82SyntaxClassOriginal::__construct as private __constructOriginal;
        TestPhp80To82SyntaxClassOriginal::describe as private describeOriginal;
    }
    public function __construct(string $label = 'default', \ArrayObject $items = new \ArrayObject([1, 2, 3]))
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            '__construct',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp80To82SyntaxClass->__construct')),
            ],
            $this->__constructOriginal(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$label, $items], 0, \func_num_args()));
    }
    public function describe(?\ArrayObject $extra = null): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'describe',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp80To82SyntaxClass->describe')),
            ],
            $this->describeOriginal(...),
        );
        return $__joinPoint->__invoke($this, \array_slice([$extra], 0, \func_num_args()));
    }
}