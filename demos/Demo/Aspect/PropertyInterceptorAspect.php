<?php

declare(strict_types=1);
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Demo\Example\PropertyDemo;
use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
use Go\Lang\Attribute\Around;

/**
 * Property interceptor can intercept access to class properties
 */
class PropertyInterceptorAspect implements Aspect
{
    /**
     * Advice that controls access to the properties
     *
     * @param FieldAccess<PropertyDemo> $fieldAccess
     */
    #[Around("access(public|protected|private Demo\Example\PropertyDemo->*)")]
    public function aroundFieldAccess(FieldAccess $fieldAccess): void
    {
        $isRead = $fieldAccess->getAccessType() === FieldAccessType::READ;
        // proceed all internal advices
        $fieldAccess->proceed();

        if ($isRead) {
            // if you want to change original property value, then return it by reference
            $value = /* & */$fieldAccess->getValue();
        } else {
            // if you want to change value to set, then return it by reference
            $value = /* & */$fieldAccess->getValueToSet();
        }

        echo "Calling After Interceptor for ", $fieldAccess, ", value: ", json_encode($value), PHP_EOL;
    }
}
