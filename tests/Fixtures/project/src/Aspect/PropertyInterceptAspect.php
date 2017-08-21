<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Lang\Annotation as Pointcut;

class PropertyInterceptAspect implements Aspect
{
    /**
     * @Pointcut\Before("access(private|protected|public Go\Tests\TestProject\Application\Main->*Property)")
     */
    public function interceptClassProperty(FieldAccess $access)
    {
        echo 'Class property intercepted!';
    }
}
