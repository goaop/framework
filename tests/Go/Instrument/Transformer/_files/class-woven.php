<?php
namespace Test\ns1;

class TestClass__AopProxied {

    public function publicMethod() {}

    protected function protectedMethod() {}

    public static function publicStaticMethod() {}

    protected static function protectedStaticMethod() {}

    public function publicMethodDynamicArguments($a, &$b)
    {
        $args = func_get_args();
        call_user_func_array([$this, 'publicMethodFixedArguments'], $args);
    }

    public function publicMethodFixedArguments($a, $b, $c = null) {}
}
include_once AOP_CACHE_DIR . '/_proxies/Transformer/_files/class.php/Test/ns1/TestClass.php';
