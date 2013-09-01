<?php

namespace Test\ns3;

class TestClass1__AopProxied {
    public static function test() {}
}

class TestClass1 extends TestClass1__AopProxied implements \Go\Aop\Proxy
{

    /**
     *Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();

    public static function test()
    {
        return self::$__joinPoints['static:test']->__invoke(get_called_class());
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns3\TestClass1', unserialize('a:1:{s:6:"method";a:1:{s:4:"test";b:1;}}'));

TestClass1::test();

class TestClass11__AopProxied {
    public static function test() {}
}

class TestClass11 extends TestClass11__AopProxied implements \Go\Aop\Proxy
{

    /**
     *Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();

    public static function test()
    {
        return self::$__joinPoints['static:test']->__invoke(get_called_class());
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns3\TestClass11', unserialize('a:1:{s:6:"method";a:1:{s:4:"test";b:1;}}'));

TestClass11::test();

class TestClass2__AopProxied {
    public static function test() {}
}

class TestClass2 extends TestClass2__AopProxied implements \Go\Aop\Proxy
{

    /**
     *Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();

    public static function test()
    {
        return self::$__joinPoints['static:test']->__invoke(get_called_class());
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns3\TestClass2', unserialize('a:1:{s:6:"method";a:1:{s:4:"test";b:1;}}'));

TestClass2::test();
