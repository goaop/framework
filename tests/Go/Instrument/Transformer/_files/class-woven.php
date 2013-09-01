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
        call_user_func_array(array($this, 'publicMethodFixedArguments'), $args);
    }

    public function publicMethodFixedArguments($a, $b, $c = null) {}
}


class TestClass extends TestClass__AopProxied implements \Go\Aop\Proxy
{


    /**
     *Property was created automatically, do not change it manually
     */
    private static $__joinPoints = array();


    protected function protectedMethod()
    {
        return self::$__joinPoints['method:protectedMethod']->__invoke($this);
    }


    protected static function protectedStaticMethod()
    {
        return self::$__joinPoints['static:protectedStaticMethod']->__invoke(get_called_class());
    }


    public function publicMethod()
    {
        return self::$__joinPoints['method:publicMethod']->__invoke($this);
    }

    public function publicMethodDynamicArguments($a, &$b)
    {
        $argsList = func_get_args();
        return self::$__joinPoints['method:publicMethodDynamicArguments']->__invoke($this, array($a, &$b) + $argsList);
    }

    public function publicMethodFixedArguments($a, $b, $c = null)
    {
        return self::$__joinPoints['method:publicMethodFixedArguments']->__invoke($this, array($a, $b, $c));
    }

    public static function publicStaticMethod()
    {
        return self::$__joinPoints['static:publicStaticMethod']->__invoke(get_called_class());
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestClass', unserialize('a:1:{s:6:"method";a:6:{s:12:"publicMethod";b:1;s:15:"protectedMethod";b:1;s:18:"publicStaticMethod";b:1;s:21:"protectedStaticMethod";b:1;s:28:"publicMethodDynamicArguments";b:1;s:26:"publicMethodFixedArguments";b:1;}}'));
