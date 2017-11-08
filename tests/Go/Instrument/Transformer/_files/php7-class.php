<?php
declare(strict_types = 1);
namespace Test\ns1;

class TestPhp7Class
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
