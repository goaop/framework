<?php
declare(strict_types = 1);
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
    public function stringRth(string $arg) : string {}
    public function floatRth(float $arg) : float {}
    public function boolRth(bool $arg) : bool {}
    public function intRth(int $arg) : int {}
    public function callableRth(callable $arg) : callable {}
    public function arrayRth(array $arg) : array {}
    public function exceptionRth(\Exception $exception) : \Exception {}
    public function noRth(LocalException $exception) {}
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
    public function stringRth(string $arg) : string
    {
        return self::$__joinPoints['method:stringRth']->__invoke($this, [$arg]);
    }
    public function floatRth(float $arg) : float
    {
        return self::$__joinPoints['method:floatRth']->__invoke($this, [$arg]);
    }
    public function boolRth(bool $arg) : bool
    {
        return self::$__joinPoints['method:boolRth']->__invoke($this, [$arg]);
    }
    public function intRth(int $arg) : int
    {
        return self::$__joinPoints['method:intRth']->__invoke($this, [$arg]);
    }
    public function callableRth(callable $arg) : callable
    {
        return self::$__joinPoints['method:callableRth']->__invoke($this, [$arg]);
    }
    public function arrayRth(array $arg) : array
    {
        return self::$__joinPoints['method:arrayRth']->__invoke($this, [$arg]);
    }
    public function exceptionRth(\Exception $exception) : \Exception
    {
        return self::$__joinPoints['method:exceptionRth']->__invoke($this, [$exception]);
    }
    public function noRth(\Test\ns1\LocalException $exception)
    {
        return self::$__joinPoints['method:noRth']->__invoke($this, [$exception]);
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
    'stringRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->stringRth',
    ),
    'floatRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->floatRth',
    ),
    'boolRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->boolRth',
    ),
    'intRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->intRth',
    ),
    'callableRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->callableRth',
    ),
    'arrayRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->arrayRth',
    ),
    'exceptionRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->exceptionRth',
    ),
    'noRth' =>
    array (
      0 => 'advisor.Test\\ns1\\TestPhp7Class->noRth',
    ),
  ),
));
