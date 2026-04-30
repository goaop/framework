<?php
declare(strict_types = 1);

namespace Test\ns3;
trait TestClass1__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Test/ns3/TestClass1.php';
TestClass1::test();
trait TestClass11__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Test/ns3/TestClass11.php';
TestClass11::test();
trait TestClass2__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Test/ns3/TestClass2.php';
TestClass2::test();
