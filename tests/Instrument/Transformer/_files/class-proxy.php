<?php
declare(strict_types=1);
namespace Test\ns1;
class TestClass implements \Go\Aop\Proxy
{
    use \Test\ns1\TestClass__AopProxied {
        \Test\ns1\TestClass__AopProxied::publicMethod as private __aop__publicMethod;
        \Test\ns1\TestClass__AopProxied::protectedMethod as private __aop__protectedMethod;
        \Test\ns1\TestClass__AopProxied::publicStaticMethod as private __aop__publicStaticMethod;
        \Test\ns1\TestClass__AopProxied::protectedStaticMethod as private __aop__protectedStaticMethod;
        \Test\ns1\TestClass__AopProxied::publicMethodDynamicArguments as private __aop__publicMethodDynamicArguments;
        \Test\ns1\TestClass__AopProxied::publicMethodFixedArguments as private __aop__publicMethodFixedArguments;
        \Test\ns1\TestClass__AopProxied::methodWithSpecialTypeArguments as private __aop__methodWithSpecialTypeArguments;
    }
    /**
     * List of applied advices per class
     *
     * Typed as MethodInvocation because generated method bodies (method:* and static:* keys)
     * call ->__invoke() directly. Other joinpoint types stored here use explicit casts:
     *   - prop:*        ClassFieldAccess — used in generated native property hooks
     *   - staticinit:*  StaticInitializationJoinpoint — instanceof check in ClassProxyGenerator::injectJoinPoints()
     *   - init:*        ReflectionConstructorInvocation — accessed via ConstructorExecutionTransformer
     *
     * @var array<string, \Go\Aop\Intercept\MethodInvocation>
     */
    private static array $__joinPoints = [];
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
    public function methodWithSpecialTypeArguments(self $instance)
    {
        return self::$__joinPoints['method:methodWithSpecialTypeArguments']->__invoke($this, [$instance]);
    }
}
\Go\Proxy\ClassProxyGenerator::injectJoinPoints(TestClass::class, [
    'method' => [
        'publicMethod' => [
            'advisor.Test\ns1\TestClass->publicMethod',
        ],
        'protectedMethod' => [
            'advisor.Test\ns1\TestClass->protectedMethod',
        ],
        'publicStaticMethod' => [
            'advisor.Test\ns1\TestClass->publicStaticMethod',
        ],
        'protectedStaticMethod' => [
            'advisor.Test\ns1\TestClass->protectedStaticMethod',
        ],
        'publicMethodDynamicArguments' => [
            'advisor.Test\ns1\TestClass->publicMethodDynamicArguments',
        ],
        'publicMethodFixedArguments' => [
            'advisor.Test\ns1\TestClass->publicMethodFixedArguments',
        ],
        'methodWithSpecialTypeArguments' => [
            'advisor.Test\ns1\TestClass->methodWithSpecialTypeArguments',
        ],
    ],
]);
