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
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();

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
        return self::$__joinPoints['static:publicStaticMethod']->__invoke(\get_called_class());
    }

    protected static function protectedStaticMethod()
    {
        return self::$__joinPoints['static:protectedStaticMethod']->__invoke(\get_called_class());
    }
}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestFinalClass',array (
  'method' =>
  array (
    'publicMethod' =>
    array (
      0 => 'advisor.Test\\ns1\\TestFinalClass->publicMethod',
    ),
    'protectedMethod' =>
    array (
      0 => 'advisor.Test\\ns1\\TestFinalClass->protectedMethod',
    ),
    'publicStaticMethod' =>
    array (
      0 => 'advisor.Test\\ns1\\TestFinalClass->publicStaticMethod',
    ),
    'protectedStaticMethod' =>
    array (
      0 => 'advisor.Test\\ns1\\TestFinalClass->protectedStaticMethod',
    ),
  ),
));
