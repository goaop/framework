<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
class TestPhp7Class implements \Go\Aop\Proxy
{
    use TestPhp7ClassOriginal {
        TestPhp7ClassOriginal::stringSth as private stringSthOriginal;
        TestPhp7ClassOriginal::floatSth as private floatSthOriginal;
        TestPhp7ClassOriginal::boolSth as private boolSthOriginal;
        TestPhp7ClassOriginal::intSth as private intSthOriginal;
        TestPhp7ClassOriginal::callableSth as private callableSthOriginal;
        TestPhp7ClassOriginal::arraySth as private arraySthOriginal;
        TestPhp7ClassOriginal::variadicStringSthByRef as private variadicStringSthByRefOriginal;
        TestPhp7ClassOriginal::exceptionArg as private exceptionArgOriginal;
        TestPhp7ClassOriginal::stringRth as private stringRthOriginal;
        TestPhp7ClassOriginal::floatRth as private floatRthOriginal;
        TestPhp7ClassOriginal::boolRth as private boolRthOriginal;
        TestPhp7ClassOriginal::intRth as private intRthOriginal;
        TestPhp7ClassOriginal::callableRth as private callableRthOriginal;
        TestPhp7ClassOriginal::arrayRth as private arrayRthOriginal;
        TestPhp7ClassOriginal::exceptionRth as private exceptionRthOriginal;
        TestPhp7ClassOriginal::noRth as private noRthOriginal;
        TestPhp7ClassOriginal::returnSelf as private returnSelfOriginal;
    }
    public function stringSth(string $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'stringSth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->stringSth')),
            ],
            $this->stringSthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatSth(float $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'floatSth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->floatSth')),
            ],
            $this->floatSthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolSth(bool $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'boolSth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->boolSth')),
            ],
            $this->boolSthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intSth(int $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'intSth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->intSth')),
            ],
            $this->intSthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableSth(callable $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'callableSth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->callableSth')),
            ],
            $this->callableSthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arraySth(array $arg)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'arraySth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->arraySth')),
            ],
            $this->arraySthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function variadicStringSthByRef(string &...$args)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'variadicStringSthByRef',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->variadicStringSthByRef')),
            ],
            $this->variadicStringSthByRefOriginal(...),
        );
        return $__joinPoint->__invoke($this, $args);
    }
    public function exceptionArg(\Exception $exception, \Test\ns1\Exception $localException)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'exceptionArg',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->exceptionArg')),
            ],
            $this->exceptionArgOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$exception, $localException]);
    }
    public function stringRth(string $arg): string
    {
        /** @var DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'stringRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->stringRth')),
            ],
            $this->stringRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatRth(float $arg): float
    {
        /** @var DynamicMethodInvocation<self, float> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'floatRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->floatRth')),
            ],
            $this->floatRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolRth(bool $arg): bool
    {
        /** @var DynamicMethodInvocation<self, bool> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'boolRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->boolRth')),
            ],
            $this->boolRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intRth(int $arg): int
    {
        /** @var DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'intRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->intRth')),
            ],
            $this->intRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableRth(callable $arg): callable
    {
        /** @var DynamicMethodInvocation<self, callable> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'callableRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->callableRth')),
            ],
            $this->callableRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arrayRth(array $arg): array
    {
        /** @var DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'arrayRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->arrayRth')),
            ],
            $this->arrayRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function exceptionRth(\Exception $exception): \Exception
    {
        /** @var DynamicMethodInvocation<self, \Exception> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'exceptionRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->exceptionRth')),
            ],
            $this->exceptionRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function noRth(\Test\ns1\LocalException $exception)
    {
        /** @var DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'noRth',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->noRth')),
            ],
            $this->noRthOriginal(...),
        );
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function returnSelf(): self
    {
        /** @var DynamicMethodInvocation<self, self> $__joinPoint */
        static $__joinPoint = InterceptorInjector::forMethod(
            self::class,
            'returnSelf',
            [
                Interceptor::before(The::advice('advisor.Test\ns1\TestPhp7Class->returnSelf')),
            ],
            $this->returnSelfOriginal(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
