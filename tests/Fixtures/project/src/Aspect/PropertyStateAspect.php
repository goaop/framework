<?php

declare(strict_types=1);

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Exception\InvocationException;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
use Go\Lang\Attribute\Around;
use Go\Tests\TestProject\Application\PropertyStateDemo;

/**
 * Logs the state every access of PropertyStateDemo reports to its advice (#819)
 */
class PropertyStateAspect implements Aspect
{
    /**
     * @var list<string>
     */
    public static array $log = [];

    #[Around("access(public Go\Tests\TestProject\Application\PropertyStateDemo->*)")]
    public function aroundPropertyAccess(FieldAccess $access): mixed
    {
        $name = $access->getField()->name;
        try {
            $state = var_export($access->getValue(), true);
        } catch (InvocationException) {
            $state = 'uninitialized';
        }
        if ($access->getAccessType() === FieldAccessType::Read) {
            self::$log[] = "read {$name}: {$state}";

            // Supplies a value for an uninitialized property, like a lazy-loading advice
            return $state === 'uninitialized' ? "lazy {$name}" : $access->proceed();
        }

        $newValue    = $access->getValueToSet();
        self::$log[] = "write {$name}: {$state} -> " . var_export($newValue, true);
        if ($newValue === 'nested') {
            // A read of the same property inside the write advice: the get and set hooks share the joinpoint
            $instance = $access->getThis();
            assert($instance instanceof PropertyStateDemo);
            self::$log[] = 'nested read returned ' . var_export($instance->nullable, true);
        }

        return $access->proceed();
    }
}
