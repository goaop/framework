<?php
declare(strict_types = 1);

namespace Test\ns3;
trait TestClass1__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-classes.php';
TestClass1::test();
trait TestClass11__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-classes.php';
TestClass11::test();
trait TestClass2__AopProxied {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-classes.php';
TestClass2::test();
