<?php
namespace Test\ns1;

class TestClass {

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

    /**
     * `self` value is handled on AST level via SelfValueTransformer class
     * @see \Go\Instrument\Transformer\SelfValueTransformer
     */
    public function methodWithSpecialTypeArguments(/* self */ $instance) {}
}

