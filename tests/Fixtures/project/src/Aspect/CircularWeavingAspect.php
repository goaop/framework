<?php

namespace Go\Tests\TestProject\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Tests\TestProject\Application\CircularyWeaved;
use Go\Lang\Annotation as Pointcut;

class CircularWeavingAspect implements Aspect
{
    private $circularyWeaved;

    public function __construct(CircularyWeaved $circularyWeaved)
    {
        $this->circularyWeaved = $circularyWeaved;
    }

    /**
     * Intercepts doSomething()
     *
     * @param MethodInvocation $invocation
     *
     * @Pointcut\After("execution(public Go\Tests\TestProject\Application\CircularyWeaved->youCanNotWeaveMe(*))")
     */
    public function failToIntercept(MethodInvocation $invocation)
    {
        $this->circularyWeaved->youCanNotWeaveMe();
    }
}
