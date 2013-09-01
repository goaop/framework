<?php
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
     *Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();

    protected function protectedMethod()
    {
        return self::$__joinPoints['method:protectedMethod']->__invoke($this);
    }

    protected static function protectedStaticMethod()
    {
        return self::$__joinPoints['static:protectedStaticMethod']->__invoke(get_called_class());
    }

    public function publicMethod()
    {
        return self::$__joinPoints['method:publicMethod']->__invoke($this);
    }

    public static function publicStaticMethod()
    {
        return self::$__joinPoints['static:publicStaticMethod']->__invoke(get_called_class());
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestFinalClass', unserialize('a:1:{s:6:"method";a:4:{s:12:"publicMethod";b:1;s:15:"protectedMethod";b:1;s:18:"publicStaticMethod";b:1;s:21:"protectedStaticMethod";b:1;}}'));
