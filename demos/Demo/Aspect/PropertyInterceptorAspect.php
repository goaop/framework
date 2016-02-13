<?php
/*
 * Go! AOP framework
 *
 * @copyright Copyright 2014, Lisachenko Alexander <lisachenko.it@gmail.com>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Demo\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Annotation\Around;

/**
 * Property interceptor can intercept an access to the public and protected properties
 *
 * Be aware, it's very tricky and will not work for indirect modification, such as array_pop($this->property);
 */
class PropertyInterceptorAspect implements Aspect
{

    /**
     * Advice that controls an access to the properties
     *
     * @param FieldAccess $fieldAccess Joinpoint
     *
     * @Around("access(public|protected Demo\Example\PropertyDemo->*)")
     * @return mixed
     */
    public function aroundFieldAccess(FieldAccess $fieldAccess)
    {
        $value = $fieldAccess->proceed();
        echo "Calling Around Interceptor for ", $fieldAccess, ", value: ", json_encode($value), PHP_EOL;

        // $value = 666; You can change the return value for read/write operations in advice!
        return $value;
    }
}
