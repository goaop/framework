<?php
declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute as Pointcut;

/**
 * Method-execution advices for the z-engine driver fixtures (the join point kind the driver supports)
 */
class ZEngineAspect implements Aspect
{
    /**
     * Advice calls, in order: "<declaring class>::<method> on <class of $this or called class>"
     *
     * @var list<string>
     */
    public static array $log = [];

    #[Pointcut\Before("execution(public Go\Tests\TestProject\Application\ZEngineParent->greet(*))
        || execution(public Go\Tests\TestProject\Application\ZEngineChild->*(*))
        || execution(public Go\Tests\TestProject\Application\ZEngineLate->*(*))")]
    public function beforeMethod(MethodInvocation $invocation): void
    {
        $method = $invocation->getMethod();
        $target = $invocation->getThis();
        self::$log[] = sprintf(
            'before %s::%s on %s',
            $method->class,
            $method->name,
            is_object($target) ? $target::class : (is_string($target) ? $target : get_debug_type($target)),
        );
    }

    #[Pointcut\Around("execution(public Go\Tests\TestProject\Application\ZEngineChild->greet(*))")]
    public function aroundGreet(MethodInvocation $invocation): string
    {
        $result = $invocation->proceed();
        self::$log[] = 'around greet got ' . get_debug_type($result);

        return strtoupper((string) $result);
    }
}
