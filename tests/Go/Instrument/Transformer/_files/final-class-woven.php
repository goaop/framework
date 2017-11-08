<?php
declare(strict_types = 1);
namespace Test\ns1;

class TestFinalClass__AopProxied {

    public function publicMethod() {}

    protected function protectedMethod() {}

    public static function publicStaticMethod() {}

    protected static function protectedStaticMethod() {}
}

final class TestFinalClass extends TestFinalClass__AopProxied implements \Go\Aop\Proxy
{

    /**
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = [
        'method' => [
            'publicMethod' => [
                'advisor.Test\\ns1\\TestFinalClass->publicMethod'
            ],
            'protectedMethod' => [
                'advisor.Test\\ns1\\TestFinalClass->protectedMethod'
            ],
            'publicStaticMethod' => [
                'advisor.Test\\ns1\\TestFinalClass->publicStaticMethod'
            ],
            'protectedStaticMethod' => [
                'advisor.Test\\ns1\\TestFinalClass->protectedStaticMethod'
            ]
        ]
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
}
\Go\Proxy\ClassProxy::injectJoinPoints(TestFinalClass::class);
