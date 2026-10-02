<?php
declare(strict_types=1);
namespace Test\ns1;
use Go\Aop\Framework\InterceptorInjector;
use Go\Aop\Framework\Interceptor;
use Go\Aop\Framework\The;
use Go\Aop\Intercept\DynamicMethodInvocation;
class TestPhp7Class implements \Go\Aop\Proxy
{
    use TestPhp7ClassOriginalTrait {
        TestPhp7ClassOriginalTrait::stringSth as private stringSthOriginalAlias;
        TestPhp7ClassOriginalTrait::floatSth as private floatSthOriginalAlias;
        TestPhp7ClassOriginalTrait::boolSth as private boolSthOriginalAlias;
        TestPhp7ClassOriginalTrait::intSth as private intSthOriginalAlias;
        TestPhp7ClassOriginalTrait::callableSth as private callableSthOriginalAlias;
        TestPhp7ClassOriginalTrait::arraySth as private arraySthOriginalAlias;
        TestPhp7ClassOriginalTrait::variadicStringSthByRef as private variadicStringSthByRefOriginalAlias;
        TestPhp7ClassOriginalTrait::exceptionArg as private exceptionArgOriginalAlias;
        TestPhp7ClassOriginalTrait::stringRth as private stringRthOriginalAlias;
        TestPhp7ClassOriginalTrait::floatRth as private floatRthOriginalAlias;
        TestPhp7ClassOriginalTrait::boolRth as private boolRthOriginalAlias;
        TestPhp7ClassOriginalTrait::intRth as private intRthOriginalAlias;
        TestPhp7ClassOriginalTrait::callableRth as private callableRthOriginalAlias;
        TestPhp7ClassOriginalTrait::arrayRth as private arrayRthOriginalAlias;
        TestPhp7ClassOriginalTrait::exceptionRth as private exceptionRthOriginalAlias;
        TestPhp7ClassOriginalTrait::noRth as private noRthOriginalAlias;
        TestPhp7ClassOriginalTrait::returnSelf as private returnSelfOriginalAlias;
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
            [self::class, 'stringSthOriginalAlias'],
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
            [self::class, 'floatSthOriginalAlias'],
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
            [self::class, 'boolSthOriginalAlias'],
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
            [self::class, 'intSthOriginalAlias'],
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
            [self::class, 'callableSthOriginalAlias'],
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
            [self::class, 'arraySthOriginalAlias'],
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
            [self::class, 'variadicStringSthByRefOriginalAlias'],
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
            [self::class, 'exceptionArgOriginalAlias'],
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
            [self::class, 'stringRthOriginalAlias'],
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
            [self::class, 'floatRthOriginalAlias'],
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
            [self::class, 'boolRthOriginalAlias'],
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
            [self::class, 'intRthOriginalAlias'],
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
            [self::class, 'callableRthOriginalAlias'],
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
            [self::class, 'arrayRthOriginalAlias'],
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
            [self::class, 'exceptionRthOriginalAlias'],
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
            [self::class, 'noRthOriginalAlias'],
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
            [self::class, 'returnSelfOriginalAlias'],
        );
        return $__joinPoint->__invoke($this);
    }
}
