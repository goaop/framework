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
    public function aroundFieldAccess(FieldAccess $fieldAccess): mixed
    {
        if ($fieldAccess->getField()->isInitialized($fieldAccess->getThis())) {
            $value = match ($fieldAccess->getAccessType()) {
                FieldAccessType::READ => $fieldAccess->getValue(),
                FieldAccessType::WRITE => $fieldAccess->getValueToSet(),
            };
        } else {
            $value = '<uninitialized>';
        }

        echo "Calling Around Interceptor for ", $fieldAccess, ", value: ", json_encode($value), PHP_EOL;

        // proceed all internal advices and return value for set/get property
        return $fieldAccess->proceed();
    }
}
