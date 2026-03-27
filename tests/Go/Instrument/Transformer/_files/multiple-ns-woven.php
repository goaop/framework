<?php
declare(strict_types = 1);
namespace Test\ns1 {
    trait TestClass1__AopProxied {
        public static function test() {}
    }
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/multiple-ns.php/Test/ns1/TestClass1.php';
}
namespace Test\ns2 {
    trait TestClass2__AopProxied {
        public static function test() {}
    }
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/multiple-ns.php/Test/ns2/TestClass2.php';
}
