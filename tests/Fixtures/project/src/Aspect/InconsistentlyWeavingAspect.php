<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Tests\TestProject\Application\InconsistentlyWeavedClass;
use Go\Lang\Annotation as Pointcut;

class InconsistentlyWeavingAspect implements Aspect
{
    public function __construct(InconsistentlyWeavedClass $problematicClass)
    {
    }

    /**
     * Intercepts badlyWeaved()
     *
     * @param MethodInvocation $invocation
     *
     * @Pointcut\After("execution(public Go\Tests\TestProject\Application\InconsistentlyWeavedClass->badlyWeaved(*))")
     */
    public function weaveBadly()
    {
        echo 'I weave badly.';
    }
}
