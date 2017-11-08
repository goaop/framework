<?php

class TestClassTypehint__AopProxied {

    public function publicMethodFixedArguments(Exception $a, $b, $c = null) {}
}


class TestClassTypehint extends TestClassTypehint__AopProxied implements \Go\Aop\Proxy
{

    /**
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = [];

    public function publicMethodFixedArguments(\Exception $a, $b, $c = NULL)
    {
        return self::$__joinPoints['method:publicMethodFixedArguments']->__invoke($this, \array_slice([$a, $b, $c], 0, \func_num_args()));
    }

}
\Go\Proxy\ClassProxy::injectJoinPoints('TestClassTypehint',array (
  'method' =>
  array (
    'publicMethodFixedArguments' =>
    array (
      0 => 'advisor.TestClassTypehint->publicMethodFixedArguments',
    ),
  ),
));
