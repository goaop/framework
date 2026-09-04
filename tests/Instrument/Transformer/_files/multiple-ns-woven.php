<?php
declare(strict_types = 1);
namespace Test\ns1 {
    trait TestClass1OriginalTrait {
        public static function test() {}
    }
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-ns.php';
}
namespace Test\ns2 {
    trait TestClass2OriginalTrait {
        public static function test() {}
    }
include_once AOP_CACHE_DIR . '/Transformer/_files/multiple-ns.php';
}
