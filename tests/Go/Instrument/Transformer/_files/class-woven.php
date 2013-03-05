<?php
namespace Test\ns1;

class TestClass__AopProxied {

    public function publicMethod() {}

    protected function protectedMethod() {}

    public static function publicStaticMethod() {}

    protected static function protectedStaticMethod() {}
}


class TestClass extends TestClass__AopProxied implements \Go\Aop\Proxy
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
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestClass', unserialize('a:4:{s:19:"method:publicMethod";b:1;s:22:"method:protectedMethod";b:1;s:25:"method:publicStaticMethod";b:1;s:28:"method:protectedStaticMethod";b:1;}'));