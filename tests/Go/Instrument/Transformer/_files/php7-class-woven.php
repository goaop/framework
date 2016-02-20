<?php
namespace Test\ns1;
class TestPhp7Class__AopProxied
{
    public function stringSth(string $arg) {}
    public function floatSth(float $arg) {}
    public function boolSth(bool $arg) {}
    public function intSth(int $arg) {}
    public function callableSth(callable $arg) {}
    public function arraySth(array $arg) {}
    public function variadicStringSthByRef(string &...$args) {}
    public function exceptionArg(\Exception $exception, Exception $localException) {}
}
class TestPhp7Class extends TestPhp7Class__AopProxied implements \Go\Aop\Proxy
{
    /**
     * Property was created automatically, do not change it manually
     */
    private static $__joinPoints = [];
    public function stringSth(string $arg)
    {
        return self::$__joinPoints['method:stringSth']->__invoke($this, [$arg]);
    }
    public function floatSth(float $arg)
    {
        return self::$__joinPoints['method:floatSth']->__invoke($this, [$arg]);
    }
    public function boolSth(bool $arg)
    {
        return self::$__joinPoints['method:boolSth']->__invoke($this, [$arg]);
    }
    public function intSth(int $arg)
    {
        return self::$__joinPoints['method:intSth']->__invoke($this, [$arg]);
    }
    public function callableSth(callable $arg)
    {
        return self::$__joinPoints['method:callableSth']->__invoke($this, [$arg]);
    }
    public function arraySth(array $arg)
    {
        return self::$__joinPoints['method:arraySth']->__invoke($this, [$arg]);
    }
    public function variadicStringSthByRef(string &...$args)
    {
        return self::$__joinPoints['method:variadicStringSthByRef']->__invoke($this, $args);
    }
    public function exceptionArg(\Exception $exception, \Test\ns1\Exception $localException)
    {
        return self::$__joinPoints['method:exceptionArg']->__invoke($this, [$exception, $localException]);
    }
}
\Go\Proxy\ClassProxy::injectJoinPoints('Test\ns1\TestPhp7Class',array (
  'method' =>
  array (
    'stringSth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->stringSth',
    ),
    'floatSth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->floatSth',
    ),
    'boolSth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->boolSth',
    ),
    'intSth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->intSth',
    ),
    'callableSth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->callableSth',
    ),
    'arraySth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->arraySth',
    ),
    'variadicStringSthByRef' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->variadicStringSthByRef',
    ),
    'exceptionArg' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->exceptionArg',
    ),
  ),
));
