<?php
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
}

