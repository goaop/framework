<?php
declare(strict_types = 1);
namespace Test\ns1 {
    class TestClass1__AopProxied {
        public static function test() {}
    }


class TestClass1 extends TestClass1__AopProxied implements \Go\Aop\Proxy
{

    /**
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = [];

    public static function test()
    {
        return self::$__joinPoints['static:test']->__invoke(static::class);
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestClass1',array (
  'method' =>
  array (
    'test' =>
    array (
      0 => 'advisor.Test\\ns1\\TestClass1->test',
    ),
  ),
));

}

namespace Test\ns2 {
    class TestClass2__AopProxied {
        public static function test() {}
    }


class TestClass2 extends TestClass2__AopProxied implements \Go\Aop\Proxy
{

    /**
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = [];

    public static function test()
    {
        return self::$__joinPoints['static:test']->__invoke(static::class);
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns2\TestClass2',array (
  'method' =>
  array (
    'test' =>
    array (
      0 => 'advisor.Test\\ns2\\TestClass2->test',
    ),
  ),
));

}
