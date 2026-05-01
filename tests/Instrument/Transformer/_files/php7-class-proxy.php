<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Intercept\DynamicMethodInvocation;
class TestPhp7Class implements \Go\Aop\Proxy
{
    use \Test\ns1\TestPhp7Class__AopProxied {
        \Test\ns1\TestPhp7Class__AopProxied::stringSth as private __aop__stringSth;
        \Test\ns1\TestPhp7Class__AopProxied::floatSth as private __aop__floatSth;
        \Test\ns1\TestPhp7Class__AopProxied::boolSth as private __aop__boolSth;
        \Test\ns1\TestPhp7Class__AopProxied::intSth as private __aop__intSth;
        \Test\ns1\TestPhp7Class__AopProxied::callableSth as private __aop__callableSth;
        \Test\ns1\TestPhp7Class__AopProxied::arraySth as private __aop__arraySth;
        \Test\ns1\TestPhp7Class__AopProxied::variadicStringSthByRef as private __aop__variadicStringSthByRef;
        \Test\ns1\TestPhp7Class__AopProxied::exceptionArg as private __aop__exceptionArg;
        \Test\ns1\TestPhp7Class__AopProxied::stringRth as private __aop__stringRth;
        \Test\ns1\TestPhp7Class__AopProxied::floatRth as private __aop__floatRth;
        \Test\ns1\TestPhp7Class__AopProxied::boolRth as private __aop__boolRth;
        \Test\ns1\TestPhp7Class__AopProxied::intRth as private __aop__intRth;
        \Test\ns1\TestPhp7Class__AopProxied::callableRth as private __aop__callableRth;
        \Test\ns1\TestPhp7Class__AopProxied::arrayRth as private __aop__arrayRth;
        \Test\ns1\TestPhp7Class__AopProxied::exceptionRth as private __aop__exceptionRth;
        \Test\ns1\TestPhp7Class__AopProxied::noRth as private __aop__noRth;
        \Test\ns1\TestPhp7Class__AopProxied::returnSelf as private __aop__returnSelf;
    }
    public function stringSth(string $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'stringSth', ['advisor.Test\ns1\TestPhp7Class->stringSth'], $this->__aop__stringSth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatSth(float $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'floatSth', ['advisor.Test\ns1\TestPhp7Class->floatSth'], $this->__aop__floatSth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolSth(bool $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'boolSth', ['advisor.Test\ns1\TestPhp7Class->boolSth'], $this->__aop__boolSth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intSth(int $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'intSth', ['advisor.Test\ns1\TestPhp7Class->intSth'], $this->__aop__intSth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableSth(callable $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'callableSth', ['advisor.Test\ns1\TestPhp7Class->callableSth'], $this->__aop__callableSth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arraySth(array $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'arraySth', ['advisor.Test\ns1\TestPhp7Class->arraySth'], $this->__aop__arraySth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function variadicStringSthByRef(string &...$args)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'variadicStringSthByRef', ['advisor.Test\ns1\TestPhp7Class->variadicStringSthByRef'], $this->__aop__variadicStringSthByRef(...));
        return $__joinPoint->__invoke($this, $args);
    }
    public function exceptionArg(\Exception $exception, \Test\ns1\Exception $localException)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'exceptionArg', ['advisor.Test\ns1\TestPhp7Class->exceptionArg'], $this->__aop__exceptionArg(...));
        return $__joinPoint->__invoke($this, [$exception, $localException]);
    }
    public function stringRth(string $arg): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'stringRth', ['advisor.Test\ns1\TestPhp7Class->stringRth'], $this->__aop__stringRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatRth(float $arg): float
    {
        /** @var DynamicMethodInvocation<self, float> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'floatRth', ['advisor.Test\ns1\TestPhp7Class->floatRth'], $this->__aop__floatRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolRth(bool $arg): bool
    {
        /** @var DynamicMethodInvocation<self, bool> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'boolRth', ['advisor.Test\ns1\TestPhp7Class->boolRth'], $this->__aop__boolRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intRth(int $arg): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'intRth', ['advisor.Test\ns1\TestPhp7Class->intRth'], $this->__aop__intRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableRth(callable $arg): callable
    {
        /** @var DynamicMethodInvocation<self, callable> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'callableRth', ['advisor.Test\ns1\TestPhp7Class->callableRth'], $this->__aop__callableRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arrayRth(array $arg): array
    {
        /** @var DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'arrayRth', ['advisor.Test\ns1\TestPhp7Class->arrayRth'], $this->__aop__arrayRth(...));
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function exceptionRth(\Exception $exception): \Exception
    {
        /** @var DynamicMethodInvocation<self, \Exception> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'exceptionRth', ['advisor.Test\ns1\TestPhp7Class->exceptionRth'], $this->__aop__exceptionRth(...));
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function noRth(\Test\ns1\LocalException $exception)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'noRth', ['advisor.Test\ns1\TestPhp7Class->noRth'], $this->__aop__noRth(...));
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function returnSelf(): self
    {
        /** @var DynamicMethodInvocation<self, self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(self::class, 'returnSelf', ['advisor.Test\ns1\TestPhp7Class->returnSelf'], $this->__aop__returnSelf(...));
        return $__joinPoint->__invoke($this);
    }
}
