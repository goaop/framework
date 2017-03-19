<?php
declare(strict_types = 1);
namespace Test\ns1;

class TestClass {

    public function publicMethod() {}

    protected function protectedMethod() {}

    public static function publicStaticMethod() {}

    protected static function protectedStaticMethod() {}

    public function publicMethodDynamicArguments($a, &$b)
    {
        $args = func_get_args();
        call_user_func_array(array($this, 'publicMethodFixedArguments'), $args);
    }

    public function publicMethodFixedArguments($a, $b, $c = null) {}
}

