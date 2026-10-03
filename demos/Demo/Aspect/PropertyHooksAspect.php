<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2026, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Demo\Example\ProductDemo;
use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
use Go\Lang\Attribute\Around;

/**
 * Intercepts property access through native PHP 8.4 property hooks
 */
class PropertyHooksAspect implements Aspect
{
    /**
     * Traces every read and write of the product properties
     *
     * The pointcut matches all public properties, but $sku already has its own hook: it is skipped,
     * because the framework can not compose its generated hooks with the hooks written by hand.
     *
     * @param FieldAccess<ProductDemo> $fieldAccess
     */
    #[Around("access(public Demo\Example\ProductDemo->*)")]
    public function aroundPropertyAccess(FieldAccess $fieldAccess): mixed
    {
        $value = match ($fieldAccess->getAccessType()) {
            FieldAccessType::Read  => $fieldAccess->getValue(),
            FieldAccessType::Write => $fieldAccess->getValueToSet(),
        };
        echo 'Calling Around Interceptor for ', $fieldAccess, ', value: ', json_encode($value), PHP_EOL;

        return $fieldAccess->proceed();
    }
}
