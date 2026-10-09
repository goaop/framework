<?php
declare(strict_types = 1);

namespace Test\ns3;
trait TestClass1OriginalTrait {
    public static function test() {}
}
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-classes.php';
TestClass1::test();
trait TestClass11OriginalTrait {
    public static function test() {}
}
TestClass11::test();
trait TestClass2OriginalTrait {
    public static function test() {}
}
TestClass2::test();
