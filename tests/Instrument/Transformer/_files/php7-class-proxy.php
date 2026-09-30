<?php
declare(strict_types=1);
namespace Test\ns1;
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
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'stringSth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->stringSth')),
            ],
            $this->stringSthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatSth(float $arg)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'floatSth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->floatSth')),
            ],
            $this->floatSthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolSth(bool $arg)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'boolSth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->boolSth')),
            ],
            $this->boolSthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intSth(int $arg)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'intSth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->intSth')),
            ],
            $this->intSthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableSth(callable $arg)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'callableSth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->callableSth')),
            ],
            $this->callableSthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arraySth(array $arg)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'arraySth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->arraySth')),
            ],
            $this->arraySthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function variadicStringSthByRef(string &...$args)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'variadicStringSthByRef',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->variadicStringSthByRef')),
            ],
            $this->variadicStringSthByRefOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, $args);
    }
    public function exceptionArg(\Exception $exception, \Test\ns1\Exception $localException)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'exceptionArg',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->exceptionArg')),
            ],
            $this->exceptionArgOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$exception, $localException]);
    }
    public function stringRth(string $arg): string
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, string> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'stringRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->stringRth')),
            ],
            $this->stringRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function floatRth(float $arg): float
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, float> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'floatRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->floatRth')),
            ],
            $this->floatRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function boolRth(bool $arg): bool
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, bool> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'boolRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->boolRth')),
            ],
            $this->boolRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function intRth(int $arg): int
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, int> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'intRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->intRth')),
            ],
            $this->intRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function callableRth(callable $arg): callable
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, callable> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'callableRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->callableRth')),
            ],
            $this->callableRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function arrayRth(array $arg): array
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, array> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'arrayRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->arrayRth')),
            ],
            $this->arrayRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$arg]);
    }
    public function exceptionRth(\Exception $exception): \Exception
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, \Exception> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'exceptionRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->exceptionRth')),
            ],
            $this->exceptionRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function noRth(\Test\ns1\LocalException $exception)
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'noRth',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->noRth')),
            ],
            $this->noRthOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this, [$exception]);
    }
    public function returnSelf(): self
    {
        /** @var \Go\Aop\Intercept\DynamicMethodInvocation<self, self> $__joinPoint */
        static $__joinPoint = \Go\Aop\Framework\InterceptorInjector::forMethod(
            self::class,
            'returnSelf',
            [
                \Go\Aop\Framework\Interceptor::before(\Go\Aop\Framework\The::advice('advisor.Test\ns1\TestPhp7Class->returnSelf')),
            ],
            $this->returnSelfOriginalAlias(...),
        );
        return $__joinPoint->__invoke($this);
    }
}
