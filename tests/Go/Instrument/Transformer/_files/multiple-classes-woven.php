<?php
namespace Test\ns3;
class TestClass1__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/multiple-classes.php/Test/ns3/TestClass1.php';
TestClass1::test();
class TestClass11__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/multiple-classes.php/Test/ns3/TestClass11.php';
TestClass11::test();
class TestClass2__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/multiple-classes.php/Test/ns3/TestClass2.php';
TestClass2::test();
