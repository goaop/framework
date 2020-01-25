<?php
declare(strict_types=1);
namespace Test\ns1;

class TestClass extends TestClass__AopProxied implements \Go\Aop\Proxy
{
    /**
     * List of applied advices per class
     */
    private static $__joinPoints = [
        'method' => [
            'publicMethod' => [
                'advisor.Test\\ns1\\TestClass->publicMethod',
            ],
            'protectedMethod' => [
                'advisor.Test\\ns1\\TestClass->protectedMethod',
            ],
            'publicStaticMethod' => [
                'advisor.Test\\ns1\\TestClass->publicStaticMethod',
            ],
            'protectedStaticMethod' => [
                'advisor.Test\\ns1\\TestClass->protectedStaticMethod',
            ],
            'publicMethodDynamicArguments' => [
                'advisor.Test\\ns1\\TestClass->publicMethodDynamicArguments',
            ],
            'publicMethodFixedArguments' => [
                'advisor.Test\\ns1\\TestClass->publicMethodFixedArguments',
            ],
            'methodWithSpecialTypeArguments' => [
                'advisor.Test\\ns1\\TestClass->methodWithSpecialTypeArguments',
            ],
        ],
    ];

    public function publicMethod()
    {
        return self::$__joinPoints['method:publicMethod']->__invoke($this);
    }

    protected function protectedMethod()
    {
        return self::$__joinPoints['method:protectedMethod']->__invoke($this);
    }

    public static function publicStaticMethod()
    {
        return self::$__joinPoints['static:publicStaticMethod']->__invoke(static::class);
    }

    protected static function protectedStaticMethod()
    {
        return self::$__joinPoints['static:protectedStaticMethod']->__invoke(static::class);
    }

    public function publicMethodDynamicArguments($a, &$b)
    {
        return self::$__joinPoints['method:publicMethodDynamicArguments']->__invoke($this, [$a, &$b]);
    }

    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        return self::$__joinPoints['method:publicMethodFixedArguments']->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }

    /**
     * `self` value is handled on AST level via SelfValueTransformer class
     * @see \Go\Instrument\Transformer\SelfValueTransformer
     */
    public function methodWithSpecialTypeArguments($instance)
    {
        return self::$__joinPoints['method:methodWithSpecialTypeArguments']->__invoke($this, [$instance]);
    }
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(TestClass::class);
